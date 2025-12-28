<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

namespace App\Modules\Pterodactyl;

use App\Abstracts\AbstractServerType;
use App\Contracts\Provisioning\ImportServiceInterface;
use App\Contracts\Provisioning\ServerTypeInterface;
use App\DTO\Provisioning\ServiceStateChangeDTO;
use App\Events\GameHostingChangedEvent;
use App\Exceptions\ProductConfigNotFoundException;
use App\Exceptions\ServiceDeliveryException;
use App\Models\Account\Customer;
use App\Models\Provisioning\Server;
use App\Models\Provisioning\Service;
use App\Models\Store\Product;
use App\Modules\Pterodactyl\DTO\NodeAllocationDiagnosticDTO;
use App\Modules\Pterodactyl\DTO\PterodactylAccountDTO;
use App\Modules\Pterodactyl\DTO\PterodactylConfigDTO;
use App\Modules\Pterodactyl\DTO\PterodactylServerDTO;
use App\Modules\Pterodactyl\Models\PterodactylConfig;
use App\Modules\Wisp\Models\WispConfig;
use App\Modules\Wisp\WispMail;
use GuzzleHttp\Psr7\Response as ResponseGuzzle;

class PterodactylServerType extends AbstractServerType implements ServerTypeInterface
{
    protected string $uuid = 'pterodactyl';

    protected string $title = 'Pterodactyl';

    /**
     * {@inheritDoc}
     */
    public function suspendAccount(Service $service): ServiceStateChangeDTO
    {
        if ($service->type != $this->uuid) {
            return new ServiceStateChangeDTO($service, false, 'Service type' . $service->type . ' is not ' . $this->uuid . ', cannot suspend account');
        }
        if ($service->server == null) {
            return new ServiceStateChangeDTO($service, true, 'No server found for service ' . $service->id);
        }
        try {
            $server = PterodactylServerDTO::getServerFromExternalId($service);
        } catch (\Exception $e) {
            return new ServiceStateChangeDTO($service, true, 'the server already deleted');
        }
        $server = PterodactylServerDTO::getServerFromExternalId($service);
        $serverResult = $server->suspend($service);
        if ($serverResult->successful()) {
            return new ServiceStateChangeDTO($service, true, 'Server suspended');
        }

        return new ServiceStateChangeDTO($service, false, 'Error while suspending server : ' . $serverResult->formattedErrors());
    }

    public function testConnection(array $params): \App\DTO\Provisioning\ConnectionResponse
    {
        try {
            $server = new Server;
            $server->fill($params);

            $response = Http::callApi($server, 'servers', [], 'POST')->getResponse();
            $body = $response->json();
            $status = $response->status();
            if ($body != null && $body['errors'][0]['code'] == 'ValidationException') {
                $status = 200;
            }
            $clientResponse = Http::callApi($server, '', [], 'GET', true, 'client')->getResponse();
            $locationsResponse = Http::callApi($server, 'locations')->getResponse();
            $nestsResponse = Http::callApi($server, 'nests')->getResponse();
            $locationsValid = $locationsResponse->status() == 200;
            $nestsValid = $nestsResponse->status() == 200;
            $clientValid = $clientResponse->status() == 200;
            $serversValid = $status == 200;
            $errorMessages = [];
            if (! $locationsValid) {
                $errorMessages[] = 'Invalid "locations" Permissions Statut :' . $locationsResponse->status();
            }
            if (! $nestsValid) {
                $errorMessages[] = 'Invalid "nests" Permissions Statut :' . $nestsResponse->status();
            }
            if (! $clientValid) {
                $errorMessages[] = 'Invalid "client" Permissions Statut :' . $clientResponse->status();
            }
            if (! $serversValid) {
                $errorMessages[] = 'Invalid "servers" Permissions Statut :' . $status;
            }

            if (count($errorMessages) > 0) {
                $errorMessage = implode(', ', $errorMessages);
                $response = new ResponseGuzzle(500, [], $errorMessage);
            } else {
                $response = new ResponseGuzzle(200, [], 'Application & Client : Success');
            }
        } catch (\Exception $e) {
            $response = new ResponseGuzzle(500, [], $e->getMessage());
            if ($e instanceof \GuzzleHttp\Exception\RequestException) {
                $response = $e->getResponse();
                if ($response == null) {
                    $response = new ResponseGuzzle(500, [], $e->getMessage());
                }
            }
        }

        return new \App\DTO\Provisioning\ConnectionResponse($response);
    }

    /**
     * {@inheritDoc}
     */
    public function unsuspendAccount(Service $service): ServiceStateChangeDTO
    {
        if ($service->type != $this->uuid) {
            return new ServiceStateChangeDTO($service, false, 'Service type' . $service->type . ' is not ' . $this->uuid . ', cannot unsuspend account');
        }
        if ($service->server == null) {
            return new ServiceStateChangeDTO($service, true, 'No server found for service ' . $service->id);
        }
        $server = PterodactylServerDTO::getServerFromExternalId($service);
        $serverResult = $server->unsuspend($service);
        if ($serverResult->successful()) {
            return new ServiceStateChangeDTO($service, true, 'Server unsuspended');
        }

        return new ServiceStateChangeDTO($service, false, 'Error while unsuspecting server : ' . $serverResult->formattedErrors());
    }

    /**
     * {@inheritDoc}
     */
    public function expireAccount(Service $service): ServiceStateChangeDTO
    {
        if ($service->type != $this->uuid) {
            return new ServiceStateChangeDTO($service, false, 'Service type' . $service->type . ' is not ' . $this->uuid . ', cannot terminate account');
        }
        if ($service->server == null) {
            return new ServiceStateChangeDTO($service, true, 'No server found for service ' . $service->id);
        }
        $server = PterodactylServerDTO::getServerFromExternalId($service);
        $serverResult = $server->delete($service);
        if ($serverResult->successful()) {
            if (array_key_exists('domain_subdomain', $service->data) && app('extension')->extensionIsEnabled('cloudflaresubdomains')) {
                event(new GameHostingChangedEvent($service, 'expired', $service->data['domain_subdomain']));
            }
            return new ServiceStateChangeDTO($service, true, 'Server terminated');
        }

        return new ServiceStateChangeDTO($service, false, 'Error while terminating server : ' . $serverResult->formattedErrors());
    }

    /**
     * {@inheritDoc}
     */
    public function findServer(Product $product): ?Server
    {
        $config = self::getConfigClass($product)::where('product_id', $product->id)->first();
        if ($config == null) {
            throw new ProductConfigNotFoundException('No ' . $this->uuid . ' config found for product ' . $product->name);
        }

        return Server::find($config->server_id);
    }

    public function createAccount(Service $service): ServiceStateChangeDTO
    {
        if ($service->type != $this->uuid) {
            return new ServiceStateChangeDTO($service, false, 'Service type' . $service->type . ' is not ' . $this->uuid . ', cannot create account');
        }
        if ($service->server == null) {
            return new ServiceStateChangeDTO($service, false, 'No server found for service ' . $service->id);
        }
        if ($service->product == null) {
            return new ServiceStateChangeDTO($service, false, 'No product found for service ' . $service->id);
        }
        /** @var PterodactylConfig|null $config */
        $config = self::getConfigClass($service->product)::where('product_id', $service->product_id)->first();
        if ($config == null) {
            return new ServiceStateChangeDTO($service, false, 'No config found for product ' . $service->product_id);
        }
        if ($service->server == null) {
            return new ServiceStateChangeDTO($service, false, 'No server found for service ' . $service->id);
        }
        $data = $service->data;
        $user = $service->customer;
        $server = $service->server;
        // prévoir si l'utilisateur a déjà un compte pterodactyl crée au avant la commande actuel
        $resetPassword = true;
        if (Service::where('type', $this->uuid)->where('customer_id', $user->id)->where('server_id', $server->id)->count() > 0) {
            $resetPassword = false;
        }
        $userAccount = PterodactylAccountDTO::getUserAccount($user, $server, $service, $resetPassword);
        $eggs = $config->eggs;
        if (count($eggs) == 1 || ! array_key_exists('nestId', $data)) {
            $first = current($eggs);
            [$eggId, $nestId] = explode(PterodactylConfig::DELIMITER, $first);
        } else {
            $nestId = $data['nestId'] ?? '';
            $eggId = $data['eggId'] ?? '';
        }
        [$environment, $docker, $startup] = $this->getEnvFromNest($eggId, $nestId, $server, $service);
        $dto = new PterodactylConfigDTO($service, $user, $userAccount->id, $eggId, $nestId, $docker, $startup, $environment);
        $serverResult = Http::callApi($server, 'servers', $config->toRequest($service, $dto), 'POST');
        if ($serverResult->successful(true)) {
            $serverId = $serverResult->toJson()->attributes->id;
            $data = ['server_id' => $serverId, 'domain_subdomain' => $data['domain_subdomain'] ?? null];
            if ($data['domain_subdomain'] == null) {
                unset($data['domain_subdomain']);
            }
            $service->data = $data;
            $service->save();
            $server = PterodactylServerDTO::getServerFromExternalId($service);
            $this->sendEmail($service, $server, $userAccount);
            if (array_key_exists('domain_subdomain', $service->data) && app('extension')->extensionIsEnabled('cloudflaresubdomains')) {
                event(new GameHostingChangedEvent($service, 'created', $data['domain_subdomain']));
            }
            return new ServiceStateChangeDTO($service, true, 'Server created');
        }
        if ($serverResult->isExternalIdAlreadyUsed()) {

            return new ServiceStateChangeDTO($service, true, 'Server already exists');
        }
        if (in_array($serverResult->status(), [422, 400]) && str_contains($serverResult->formattedErrors(), 'for automatic deployment')) {
            $diagnostic = NodeAllocationDiagnosticDTO::analyze($server, $config, $service);
            $errorMessage = "Automatic allocation error on node.\n" . $diagnostic->getSummary();

            return new ServiceStateChangeDTO($service, false, $errorMessage);
        }

        return new ServiceStateChangeDTO($service, false, 'Error while creating server : ' . $serverResult->formattedErrors());
    }

    public function changeCustomer(Service $service, Customer $customer): ServiceStateChangeDTO
    {
        if ($service->type != $this->uuid) {
            return new ServiceStateChangeDTO($service, false, 'Service type' . $service->type . ' is not ' . $this->uuid . ', cannot change customer');
        }
        if ($service->server == null) {
            return new ServiceStateChangeDTO($service, true, 'No server found for service ' . $service->id);
        }
        $server = PterodactylServerDTO::getServerFromExternalId($service);
        $serverResult = $server->changeOwner($service, $customer);
        if ($serverResult->successful(true)) {
            return new ServiceStateChangeDTO($service, true, 'Server owner changed');
        }

        return new ServiceStateChangeDTO($service, false, 'Error while changing server owner : ' . $serverResult->formattedErrors());
    }

    public function upgradeService(Service $service, Product $product): ServiceStateChangeDTO
    {
        if ($service->type != $this->uuid) {
            return new ServiceStateChangeDTO($service, false, 'Service type' . $service->type . ' is not ' . $this->uuid . ', cannot upgrade account');
        }
        if ($service->server == null) {
            return new ServiceStateChangeDTO($service, true, 'No server found for service ' . $service->id);
        }
        $server = PterodactylServerDTO::getServerFromExternalId($service);
        $config = self::getConfigClass($product)::where('product_id', $product->id)->first();
        if ($config == null) {
            return new ServiceStateChangeDTO($service, false, 'No config found for product ' . $product->id);
        }
        $serverResult = $server->upgrade($service, $config);
        if ($serverResult->successful(true)) {
            return new ServiceStateChangeDTO($service, true, 'Server upgraded');
        }

        return new ServiceStateChangeDTO($service, false, 'Error while upgrading server : ' . $serverResult->formattedErrors());
    }

    protected function getEnvFromNest(int $eggId, int $nestId, Server $server, Service $service)
    {
        $eggResult = Http::callApi($server, 'nests/' . $nestId . '/eggs/' . $eggId . '?include=variables');
        if (! $eggResult->successful(true)) {
            throw new ServiceDeliveryException('Error while getting egg : ' . json_encode($eggResult->toJson()), $service, $eggResult->status());
        }
        $environment = [];
        foreach ($eggResult->toJson()->attributes->relationships->variables->data as $key => $val) {
            $attr = $val->attributes;
            $var = $attr->env_variable;
            $default = $attr->default_value;

            if ($attr->env_variable == 'FIVEM_LICENSE' && (empty($default))) {
                $default = 'Change me';
            }
            $envName = $data[$attr->env_variable] ?? $default;
            $environment[$var] = $envName;
        }

        return [$environment, $eggResult->toJson()->attributes->docker_image, $eggResult->toJson()->attributes->startup];
    }

    public function importService(): ?ImportServiceInterface
    {
        return new ImportServicePterodactyl;
    }

    public function onRenew(Service $service): \App\DTO\Provisioning\ServiceStateChangeDTO
    {
        if ($service->type != $this->uuid) {
            return new ServiceStateChangeDTO($service, false, 'Service type' . $service->type . ' is not ' . $this->uuid . ', cannot renew account');
        }
        if ($service->server == null) {
            return new ServiceStateChangeDTO($service, true, 'No server found for service ' . $service->id);
        }
        $server = PterodactylServerDTO::getServerFromExternalId($service);
        $serverResult = $server->changeDescription($service);
        if ($serverResult->successful(true)) {
            return new ServiceStateChangeDTO($service, true, 'Server renewed');
        }

        return new ServiceStateChangeDTO($service, false, 'Error while renewing server : ' . $serverResult->formattedErrors());
    }

    protected function sendEmail(Service $service, PterodactylServerDTO $server, PterodactylAccountDTO $userAccount): void
    {
        if ($service->type == 'wisp') {
            if (class_exists(WispMail::class)) {
                $service->customer->notify(new WispMail($server->getAddresses(), $userAccount, $server->getServerUrl($service->server)));

                return;
            }
        }
        $service->customer->notify(new PterodactylMail($server->getAddresses(), $userAccount, $server->getServerUrl($service->server)));
    }

    protected static function getConfigClass(Product $product): string
    {
        if ($product->type == 'wisp') {
            if (class_exists(WispConfig::class)) {
                return WispConfig::class;
            }
        }

        return PterodactylConfig::class;
    }

    public function getSupportedOptions(): array
    {
        return [
            'additional_memory' => __('provisioning.admin.configoptions.keys.additional_memory'),
            'additional_disk' => __('provisioning.admin.configoptions.keys.additional_disk'),
            'additional_swap' => __('provisioning.admin.configoptions.keys.additional_swap'),
            'additional_io' => __('provisioning.admin.configoptions.keys.additional_io'),
            'additional_cpu' => __('provisioning.admin.configoptions.keys.additional_cpu'),
            'additional_databases' => __('provisioning.admin.configoptions.keys.additional_databases'),
            'additional_allocations' => __('provisioning.admin.configoptions.keys.additional_allocations'),
            'additional_backups' => __('provisioning.admin.configoptions.keys.additional_backups'),
            $this->uuid . '_location_id' => __($this->uuid . '::messages.optionstypes.location_id'),
            $this->uuid . '_dedicated_ip' => __($this->uuid . '::messages.optionstypes.dedicated_ip'),
        ];
    }
}
