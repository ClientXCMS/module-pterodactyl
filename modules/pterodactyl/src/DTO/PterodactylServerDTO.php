<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

namespace App\Modules\Pterodactyl\DTO;

use App\Exceptions\ExternalApiException;
use App\Exceptions\ServiceDeliveryException;
use App\Models\Provisioning\Server;
use App\Models\Provisioning\Service;
use App\Modules\Pterodactyl\Http;
use App\Modules\Pterodactyl\Models\PterodactylConfig;

class PterodactylServerDTO
{
    public int $id;

    public string $uuid;

    public ?string $externalId = null;

    public PterodactylAccountDTO $owner;

    public int $node;

    public string $name;

    public string $description;

    public string $identifier;

    public \stdClass $attributes;

    public function __construct(\stdClass $attributes)
    {
        $this->id = $attributes->id;
        $this->uuid = $attributes->uuid;
        $this->externalId = $attributes->external_id;
        $this->node = $attributes->node;
        $this->name = $attributes->name;
        $this->identifier = $attributes->identifier;
        $this->description = $attributes->description ?? '';
        $this->attributes = $attributes;
    }

    public static function getAllServers(Server $server)
    {
        $perPage = PterodactylAccountDTO::PER_PAGE;
        $initial = Http::callApi($server, 'servers?per_page=' . $perPage);
        $results = [];
        if ($initial->toJson() == null) {
            return [];
        }
        if (property_exists($initial->toJson(), 'errors')) {
            throw new ExternalApiException($initial->toJson()->errors[0]->detail);
        }
        foreach ($initial->toJson()->data as $value) {
            $results[] = new PterodactylServerDTO($value->attributes);
        }
        for ($i = 1; $i <= $initial->toJson()->meta->pagination->total_pages; $i++) {
            $serverResult = Http::callApi($server, "servers?per_page=$perPage&page=$i");
            foreach ($serverResult->toJson()->data as $key => $value) {
                $results[] = new PterodactylServerDTO($value->attributes);
            }
        }

        return $results;
    }

    public static function getServerFromId(Server $server, int $serverId)
    {
        $serverResult = Http::callApi($server, "servers/$serverId");
        if ($serverResult->successful()) {
            return new self($serverResult->toJson()->attributes);
        }
        throw new ExternalApiException($serverResult->formattedErrors());
    }

    public function installed()
    {
        return $this->attributes->container->installed;
    }

    public function suspended()
    {
        return $this->attributes->suspended;
    }

    public function getServerUrl(Server $server): string
    {
        $schema = $server->port == 443 ? 'https://' : 'http://';
        $ip = $server->hostname;

        return sprintf('%s%s/server/%s', $schema, $ip, $this->identifier);
    }

    public function getUtilization(Server $server)
    {
        $utilizationResult = Http::callApi($server, 'servers/' . $this->identifier . '/resources', [], 'GET', true, 'client');
        if (property_exists($utilizationResult->toJson(), 'errors')) {
            throw new ExternalApiException($utilizationResult->toJson()->errors[0]->detail);
        }
        if (property_exists($utilizationResult->toJson(), 'attributes')) {
            return $utilizationResult->toJson()->attributes;
        }

        return $utilizationResult->toJson();
    }

    public static function getServer(Service $service, PterodactylAccountDTO $dto, Server $server): ?PterodactylServerDTO
    {
        $serverResult = Http::callApi($server, "servers/external/{$service->external_id}");
        if ($serverResult->successful()) {
            if ($serverResult->toJson()->attributes->user != $dto->id) {
                throw new ServiceDeliveryException("Le serveur {$service->external_id} n'appartient pas à l'utilisateur {$dto->email}");
            }

            return new self($serverResult->toJson()->attributes);
        }

        return null;
    }

    /**
     * Permet de récupérer les serveurs d'un utilisateur
     *
     * @param  Service  $service
     */
    public static function getServersUser(PterodactylAccountDTO $dto, Server $server): array
    {
        $perPage = PterodactylAccountDTO::PER_PAGE;
        $initial = Http::callApi($server, 'servers?per_page=' . $perPage);
        if (property_exists($initial->toJson(), 'errors')) {
            throw new ExternalApiException($initial->toJson()->errors[0]->detail);
        }
        $results = [];
        foreach ($initial->toJson()->data as $key => $value) {
            if ($value->attributes->user == $dto->id) {
                $results[] = new PterodactylServerDTO($value->attributes);
            }
        }
        // Check if user is other pages
        for ($i = 1; $i <= $initial->toJson()->meta->pagination->total_pages; $i++) {
            $serverResult = Http::callApi($server, "users?per_page=$perPage&page=$i");
            foreach ($serverResult as $key => $value) {
                if ($value->attributes->user == $dto->id) {
                    $results[] = new PterodactylServerDTO($value->attributes);
                }
            }
        }

        return $results;
    }

    public static function getServerFromExternalId(Service $service): PterodactylServerDTO
    {
        $server = $service->server;
        $serverResult = Http::callApi($server, "servers/external/{$service->id}?include=allocations,utilization");
        if ($serverResult->successful()) {
            $account = PterodactylAccountDTO::getUserAccount($service->customer, $server, $service);
            if ($account->id != $serverResult->toJson()->attributes->user) {
                throw new ExternalApiException("the server {$service->id} does not belong to the user {$account->email}");
            }

            return new self($serverResult->toJson()->attributes);
        }
        throw new ExternalApiException($serverResult->formattedErrors());
    }

    public function power(Service $service, string $power)
    {
        return Http::callApi($service->server, "servers/{$this->identifier}/power", ['signal' => $power], 'POST', true, 'client');
    }

    public function suspend(Service $service)
    {
        return Http::callApi($service->server, "servers/{$this->id}/suspend", [], 'POST');
    }

    public function unsuspend(Service $service)
    {
        return Http::callApi($service->server, "servers/{$this->id}/unsuspend", [], 'POST');
    }

    public function delete(Service $service)
    {
        return Http::callApi($service->server, "servers/{$this->id}", [], 'DELETE');
    }

    public function changeDescription(Service $service)
    {
        $server = $service->server;
        $serverData = PterodactylServerDTO::getServerFromExternalId($service);
        $serverId = $serverData->id;
        $user = PterodactylAccountDTO::getUserAccount($service->customer, $server, $service);
        $data = [
            'external_id' => (string) $service->id,
            'name' => $serverData->attributes->name,
            'user' => $user->id,
            'description' => 'Exp: ' . $service->expires_at->format('d/m/y'),
        ];
        if (empty($serverData->attributes->description)) {
            unset($data['description']);
        }

        return Http::callApi($server, 'servers/' . $serverId . '/details', $data, 'PATCH');
    }

    public function getAddresses(): string
    {
        if (property_exists($this->attributes->relationships->allocations, 'data')) {
            return collect($this->attributes->relationships->allocations->data)->map(function ($data) {
                return ($data->attributes->alias ?? $data->attributes->ip) . ':' . $data->attributes->port;
            })->join(',');
        }

        return '';
    }

    public function setAlias(string $alias, Server $server)
    {
        if (property_exists($this->attributes->relationships->allocations, 'data')) {
            $allocation = collect($this->attributes->relationships->allocations->data)->first();
            if ($allocation) {
                $allocationId = $allocation->attributes->id;
                $response = Http::callApi($server, "allocations/{$allocationId}/ip-alias", [
                    'ip_alias' => $alias,
                ], 'PATCH');
            }
        }
    }

    public function autologin(Server $server)
    {
        $ssoKey = $this->getSsoKey($server);
        if (empty($ssoKey)) {
            return $this->getServerUrl($server);
        }
        $response = \Http::get("https://" . $server->hostname . '/sso-clientxcms', [
            'sso_secret' => $ssoKey,
            'user_id' => $this->attributes->user,
        ]);
        if ($response->successful()) {
            $data = $response->json();
            if (isset($data['redirect'])) {
                return $data['redirect'] . '?redirect=' . urlencode('/server/' . $this->identifier);
            }
        } else {
            $response = $response->json();
            $message = $response['success'] && !$response['success']
                ? $response['message']
                : 'Something went wrong, please contact an administrator.';

            return redirect()->back()->withError($message);
        }
        return $this->getServerUrl($server);
    }

    private function getSsoKey(Server $server)
    {
        $ssoKey = getenv('SSO_CLIENTXCMS_KEY' . $server->id);
        if (empty($ssoKey)) {
            $ssoKey = $server->hasMetadata('sso_key') ? $server->getMetadata('sso_key') : null;
            if ($ssoKey) {
                return $ssoKey;
            }
        }
        return $ssoKey;
    }

    public function isStarted(\stdClass $utilization): bool
    {
        $state = $utilization->current_state ?? $utilization->status ?? 'offline';

        return $state != 'offline' && $state != '0';
    }

    public function isOffline(\stdClass $utilization): bool
    {
        $state = $utilization->current_state ?? $utilization->status ?? 'offline';

        return $state == 'offline' || $state == '0';
    }

    public function changeOwner(Service $service, \App\Models\Account\Customer $customer)
    {
        $server = $service->server;
        $serverData = PterodactylServerDTO::getServerFromExternalId($service);
        $serverId = $serverData->id;
        $data = [
            'external_id' => (string) $service->id,
            'name' => $serverData->attributes->name,
            'user' => $customer->id,
            'description' => 'Exp: ' . $service->expires_at->format('d/m/y'),
        ];
        if (empty($serverData->attributes->description)) {
            unset($data['description']);
        }

        return Http::callApi($server, 'servers/' . $serverId . '/details', $data, 'PATCH');
    }

    public function upgrade(Service $service, PterodactylConfig $config)
    {
        $server = $service->server;
        $serverData = PterodactylServerDTO::getServerFromExternalId($service);
        $serverId = $serverData->id;
        $user = PterodactylAccountDTO::getUserAccount($service->customer, $server, $service);
        $request = Http::callApi($server, 'servers/' . $serverId);
        if (! $request->successful(true)) {
            throw new ExternalApiException($request->formattedErrors());
        }
        $data = $request->toJson()->attributes;
        $data = [
            'allocation' => $data->allocation,
            'memory' => (int) (($config->memory + $service->getOptionValue('additional_memory', 0)) * 1024),
            'swap' => $config->swap + $service->getOptionValue('additional_swap', 0),
            'disk' => (int) (($config->disk + $service->getOptionValue('additional_disk', 0)) * 1024),
            'io' => $config->io + $service->getOptionValue('additional_io', 0),
            'cpu' => ($config->cpu + $service->getOptionValue('additional_cpu', 0)),
            'feature_limits' => [
                'databases' => $config->databases + $service->getOptionValue('additional_databases', 0),
                'allocations' => $config->allocations + $service->getOptionValue('additional_allocations', 0),
                'backups' => $config->backups + $service->getOptionValue('additional_backups', 0),
            ],
        ];

        return Http::callApi($server, 'servers/' . $serverId . '/build', $data, 'PATCH');
    }

    public static function getCurrentServerAllocation(Service $service, $server, $serverData)
    {

        if (isset($serverData->allocation) && is_numeric($serverData->allocation)) {
            return (int)$serverData->allocation;
        }

        if (isset($serverData->allocation->id)) {
            return (int)$serverData->allocation->id;
        }

        if (isset($serverData->relationships->allocation->data->id)) {
            return (int)$serverData->relationships->allocation->data->id;
        }

        if (isset($serverData->relationships->allocation->data->attributes->id)) {
            logger()->info('Allocation trouvée - relationships allocation attributes', [
                'server_id' => $server->id,
                'allocation' => $serverData->relationships->allocation->data->attributes->id
            ]);
            return (int)$serverData->relationships->allocation->data->attributes->id;
        }

        if (
            isset($serverData->relationships->allocations->data) &&
            is_array($serverData->relationships->allocations->data) &&
            count($serverData->relationships->allocations->data) > 0
        ) {

            $firstAlloc = $serverData->relationships->allocations->data[0];
            $allocId = null;

            if (isset($firstAlloc->id)) {
                $allocId = $firstAlloc->id;
            } elseif (isset($firstAlloc->attributes->id)) {
                $allocId = $firstAlloc->attributes->id;
            }

            if ($allocId) {
                return (int)$allocId;
            }
        }

        $allocationsApi = \App\Modules\Pterodactyl\Http::callApi(
            $service->server,
            'servers/' . $server->id . '?include=allocations',
            [],
            'GET',
            true,
            'application'
        );

        if ($allocationsApi->successful()) {
            $data = $allocationsApi->toJson();

            if (isset($data->attributes->allocation) && is_numeric($data->attributes->allocation)) {
                return (int)$data->attributes->allocation;
            }

            if (
                isset($data->attributes->relationships->allocations->data) &&
                is_array($data->attributes->relationships->allocations->data) &&
                count($data->attributes->relationships->allocations->data) > 0
            ) {

                $firstAlloc = $data->attributes->relationships->allocations->data[0];
                $allocId = isset($firstAlloc->attributes->id) ? $firstAlloc->attributes->id : ($firstAlloc->id ?? null);

                if ($allocId) {
                    return (int)$allocId;
                }
            }
        }

        return null;
    }

    public function changeEgg(Service $service, ?string $eggId): void
    {
        $server = $service->server;
        $config = PterodactylConfig::where('product_id', $service->product_id)->first();

        $nestId = null;
        if ($config && isset($config->eggs)) {
            $eggsArray = is_array($config->eggs) ? $config->eggs : json_decode($config->eggs, true);
            if (is_array($eggsArray)) {
                foreach ($eggsArray as $eggNest) {
                    [$egg, $nest] = explode(PterodactylConfig::DELIMITER, $eggNest);
                    if ($egg == $eggId) {
                        $nestId = $nest;
                        break;
                    }
                }
            }
        }

        $serverApi = Http::callApi($server, 'servers/' . $this->id, [], 'GET', true, 'application');
        $serverData = $serverApi->toJson()->attributes ?? null;

        if (!$serverData) {
            throw new ExternalApiException(__('pterodactyl::panel.cant_get_server'));
        }

        $currentAllocation = self::getCurrentServerAllocation($service, clone $this, $serverData);
        if (!$currentAllocation) {
            throw new ExternalApiException(__('pterodactyl::panel.cant_get_allocation'));
        }

        $limits = [
            'memory' => (int) (($config->memory + $service->getOptionValue('additional_memory', 0)) * 1024),
            'swap' => $config->swap + $service->getOptionValue('additional_swap', 0),
            'disk' => (int) (($config->disk + $service->getOptionValue('additional_disk', 0)) * 1024),
            'io' => $config->io + $service->getOptionValue('additional_io', 0),
            'cpu' => ($config->cpu + $service->getOptionValue('additional_cpu', 0)),
        ];
        $feature_limits = [
            'databases' => $config->databases + $service->getOptionValue('additional_databases', 0),
            'allocations' => $config->allocations + $service->getOptionValue('additional_allocations', 0),
            'backups' => $config->backups + $service->getOptionValue('additional_backups', 0),
        ];

        $startup = $serverData->container->startup ?? $config->startup;
        $image = $serverData->container->image ?? $config->image;
        $environment = $serverData->container->environment ?? [];

        if ($eggId && $nestId) {
            $eggApi = Http::callApi($server, "nests/$nestId/eggs/$eggId?include=variables", [], 'GET', true, 'application');
            if ($eggApi->status() == 200 && isset($eggApi->toJson()->attributes)) {
                $image = $eggApi->toJson()->attributes->docker_image ?? $image;
                $startup = $eggApi->toJson()->attributes->startup ?? $startup;

                if (isset($eggApi->toJson()->attributes->relationships->variables->data)) {
                    $newEnvironment = [];
                    foreach ($eggApi->toJson()->attributes->relationships->variables->data as $key => $val) {
                        $attr = $val->attributes;
                        $var = $attr->env_variable;
                        $default = $attr->default_value;

                        if ($attr->env_variable == 'FIVEM_LICENSE' && (empty($default))) {
                            $default = 'Change me';
                        }
                        $envName = $service->data[$attr->env_variable] ?? $default;
                        $newEnvironment[$var] = $envName;
                    }
                    $environment = $newEnvironment;
                }
            }
        }

        $startupPayload = [
            'startup' => $startup,
            'environment' => (array)$environment,
            'egg' => (int)$eggId,
            'image' => $image,
            'skip_scripts' => false,
        ];

        logger()->info('ChangeEgg - Updating startup', [
            'server_id' => $this->id,
            'service_id' => $service->id,
            'payload' => $startupPayload
        ]);

        $updateStartupResult = Http::callApi(
            $server,
            'servers/' . $this->id . '/startup',
            $startupPayload,
            'PATCH',
            true,
            'application'
        );

        if (!$updateStartupResult->successful()) {
            logger()->error('ChangeEgg - Error updating startup', [
                'server_id' => $this->id,
                'error' => $updateStartupResult->formattedErrors(),
                'response' => $updateStartupResult->toJson()
            ]);
            throw new ExternalApiException(__('pterodactyl::panel.startup_error') . $updateStartupResult->formattedErrors());
        }

        $buildPayload = [
            'allocation' => (int)$currentAllocation,
            'memory' => $limits['memory'],
            'swap' => $limits['swap'],
            'disk' => $limits['disk'],
            'io' => $limits['io'],
            'cpu' => $limits['cpu'],
            'feature_limits' => $feature_limits,
        ];

        logger()->info('ChangeEgg - Updating build', [
            'server_id' => $this->id,
            'service_id' => $service->id,
            'payload' => $buildPayload
        ]);

        $updateBuildResult = Http::callApi(
            $server,
            'servers/' . $this->id . '/build',
            $buildPayload,
            'PATCH',
            true,
            'application'
        );

        if (!$updateBuildResult->successful()) {
            logger()->error('ChangeEgg - Error updating build', [
                'server_id' => $this->id,
                'error' => $updateBuildResult->formattedErrors(),
                'response' => $updateBuildResult->toJson()
            ]);
            throw new ExternalApiException(__('pterodactyl::panel.build_error') . $updateBuildResult->formattedErrors());
        }

        $reinstallResult = Http::callApi(
            $server,
            'servers/' . $this->id . '/reinstall',
            [],
            'POST',
            true,
            'application'
        );

        if (!$reinstallResult->successful()) {
            logger()->error('ChangeEgg - Error reinstalling', [
                'server_id' => $this->id,
                'error' => $reinstallResult->formattedErrors()
            ]);
            throw new ExternalApiException(__('pterodactyl::panel.reinstall_failed') . $reinstallResult->formattedErrors());
        }
    }
}
