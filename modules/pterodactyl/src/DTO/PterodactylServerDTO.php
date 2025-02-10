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
        $this->description = $attributes->description ?? "";
        $this->attributes = $attributes;
    }

    public static function getAllServers(Server $server)
    {
        $perPage = PterodactylAccountDTO::PER_PAGE;
        $initial = Http::callApi($server, "servers?per_page=". $perPage);
        $results = [];
        if ($initial->toJson() == null) {
            return [];
        }
        if (property_exists($initial->toJson(), 'errors')){
            throw new ExternalApiException($initial->toJson()->errors[0]->detail);
        }
        foreach ($initial->toJson()->data as $value) {
            $results[] = new PterodactylServerDTO($value->attributes);
        }
        for($i = 1; $i <= $initial->toJson()->meta->pagination->total_pages;$i++) {
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
        throw new ExternalApiException("the server $serverId does not exist on the panel");
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
        $utilizationResult = Http::callApi($server, 'servers/' .$this->identifier . "/resources", [], 'GET', true, 'client');
        if (property_exists($utilizationResult->toJson(), 'errors')){
            throw new ExternalApiException($utilizationResult->toJson()->errors[0]->detail);
        }
        if (property_exists($utilizationResult->toJson(), 'attributes')){
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
     * @param PterodactylAccountDTO $dto
     * @param Server $server
     * @param Service $service
     * @return array
     */
    public static function getServersUser(PterodactylAccountDTO $dto, Server $server): array
    {
        $perPage = PterodactylAccountDTO::PER_PAGE;
        $initial = Http::callApi($server, "servers?per_page=". $perPage);
        if (property_exists($initial->toJson(), 'errors')){
            throw new ExternalApiException($initial->toJson()->errors[0]->detail);
        }
        $results = [];
        foreach ($initial->toJson()->data as $key => $value) {
            if ($value->attributes->user == $dto->id) {
                $results[] = new PterodactylServerDTO($value->attributes);
            }
        }
        // Check if user is other pages
        for($i = 1; $i <= $initial->toJson()->meta->pagination->total_pages;$i++) {
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
            if ($account->id != $serverResult->toJson()->attributes->user){
                throw new ExternalApiException("the server {$service->id} does not belong to the user {$account->email}");
            }
            return new self($serverResult->toJson()->attributes);
        }
        throw new ExternalApiException("the server {$service->id} does not exist on the panel");

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
            'external_id' => (string)$service->id,
            'name' => $serverData->attributes->name,
            'user' => $user->id,
            'description' =>  "Exp: ". $service->expires_at->format('d/m/y'),
        ];
        if (empty($serverData->attributes->description)) {
            unset($data['description']);
        }
        return Http::callApi($server, 'servers/' . $serverId . '/details', $data, 'PATCH');
    }

    public function getAddresses():string
    {
        if (property_exists($this->attributes->relationships->allocations, 'data')) {
            return collect($this->attributes->relationships->allocations->data)->map(function ($data) {
                return ($data->attributes->alias ?? $data->attributes->ip)  . ":" . $data->attributes->port;
            })->join(',');
        }
        return '';
    }

    public function isStarted(\stdClass $utilization): bool
    {
        $state = $utilization->current_state ?? $utilization->status;
        return $state != 'offline' && $state != '0';
    }

    public function isOffline(\stdClass $utilization): bool
    {
        $state = $utilization->current_state ?? $utilization->status;
        return $state == 'offline' || $state == '0';
    }

    public function changeOwner(Service $service, \App\Models\Account\Customer $customer)
    {
        $server = $service->server;
        $serverData = PterodactylServerDTO::getServerFromExternalId($service);
        $serverId = $serverData->id;
        $data = [
            'external_id' => (string)$service->id,
            'name' => $serverData->attributes->name,
            'user' => $customer->id,
            'description' =>  "Exp: ". $service->expires_at->format('d/m/y'),
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
        if (!$request->successful(true)) {
            throw new ExternalApiException($request->formattedErrors());
        }
        $data = $request->toJson()->attributes;
        $data = [
                'allocation' => $data->allocation,
                'memory' => (int)(($config->memory + $service->getOptionValue('additional_memory', 0)) * 1024),
                'swap' => $config->swap + $service->getOptionValue('additional_swap', 0),
                'disk' => (int)(($config->disk + $service->getOptionValue('additional_disk', 0)) * 1024),
                'io' => $config->io + $service->getOptionValue('additional_io', 0),
                'cpu' => ($config->cpu  + $service->getOptionValue('additional_cpu', 0)),
                'feature_limits' => [
                    'databases' => $config->databases + $service->getOptionValue('additional_databases', 0),
                    'allocations' => $config->allocations + $service->getOptionValue('additional_allocations', 0),
                    'backups' => $config->backups + $service->getOptionValue('additional_backups', 0),
                ],
        ];
        return Http::callApi($server, 'servers/' . $serverId . '/build', $data, 'PATCH');
    }

}
