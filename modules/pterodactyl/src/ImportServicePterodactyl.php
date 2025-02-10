<?php
/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */
namespace App\Modules\Pterodactyl;

use App\DTO\Provisioning\ServiceStateChangeDTO;
use App\Exceptions\ExternalApiException;
use App\Models\Provisioning\Server;
use App\Models\Provisioning\Service;
use App\Models\Store\Product;
use App\Modules\Pterodactyl\DTO\PterodactylAccountDTO;
use App\Modules\Pterodactyl\DTO\PterodactylServerDTO;
use Illuminate\Validation\Rule;

class ImportServicePterodactyl implements \App\Contracts\Provisioning\ImportServiceInterface
{

    /**
     * @throws ExternalApiException
     */
    public function import(Service $service, array $data = []): ServiceStateChangeDTO
    {
        try {
            $server = $service->server;
            if ($server == null) {
                throw new ExternalApiException('No server found');
            }
            $serverId = $data['pterodactylserver_id'];
            $serverData = PterodactylServerDTO::getServerFromId($server, $serverId);
            $user = PterodactylAccountDTO::getUserAccount($service->customer, $server, $service);
            $details = [
                'external_id' => (string)$service->id,
                'name' => $serverData->attributes->name,
                'user' => $user->id,
                'description' =>  "Exp: ". $service->expires_at->format('d/m/y'),
            ];
            if ($server->type == 'wisp')
                unset($details['description']);
            $response = Http::callApi($server, 'servers/' . $serverId . '/details', $details, 'PATCH');
            if ($response->status() != 200) {
                throw new ExternalApiException($response->toJson()->errors[0]->detail);
            }
            return new ServiceStateChangeDTO($service, true, 'Service imported successfully');
        } catch (ExternalApiException $e){
            return new ServiceStateChangeDTO($service, false, $e->getTraceAsString());
        }
    }

    public function validate(): array
    {
        return [
            'pterodactylserver_id' => ['required', 'integer']
        ];
    }

    public function render(Service $service, array $permissions = [])
    {
        if ($service->server_id == null) {
            return 'No server';
        }
        $server = $service->server;
        $servers = PterodactylServerDTO::getAllServers($server);
        $servers = collect($servers)->mapWithKeys(function ($server) {
            return [$server->id => $server->name];
        });
        return view($service->type . '_admin::import', [
            'service' => $service,
            'servers' => $servers,
            'permissions' => $permissions,
        ]);
    }
}
