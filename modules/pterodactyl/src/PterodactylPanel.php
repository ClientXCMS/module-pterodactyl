<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

namespace App\Modules\Pterodactyl;

use App\Abstracts\AbstractPanelProvisioning;
use App\DTO\Provisioning\ProvisioningTabDTO;
use App\Exceptions\ExternalApiException;
use App\Models\Provisioning\Service;
use App\Modules\Pterodactyl\DTO\PterodactylServerDTO;
use Illuminate\Http\RedirectResponse;

class PterodactylPanel extends AbstractPanelProvisioning
{
    protected string $uuid = 'pterodactyl';

    protected string $offline = 'offline';

    /**
     * {@inheritDoc}
     */
    public function tabs(Service $service): array
    {
        return [
            new ProvisioningTabDTO([
                'title' => __('provisioning.connect'),
                'permission' => $this->uuid.'.panel.connect',
                'icon' => '<i class="bi bi-person-badge"></i>',
                'uuid' => 'connect',
                'newwindow' => true,
                'active' => true,
            ]),
        ];
    }

    public function render(Service $service, array $permissions = [])
    {
        $data = [
            'service' => $service,
            'permissions' => $permissions,
        ];
        try {
            if (! $service->server) {
                \Session::flash('error', __('client.alerts.servernotfound'));

                return '';
            }
            $serverResult = \App\Modules\Pterodactyl\DTO\PterodactylServerDTO::getServerFromExternalId($service);
            if (! $serverResult->installed()) {
                \Session::flash('info', __('client.alerts.servernotinstalled'));

                return '';
            }
            if ($serverResult->suspended()) {
                \Session::flash('error', __('client.alerts.service_suspended'));

                return '';
            }
            $data['server'] = $serverResult;
            $data['href'] = $serverResult->getServerUrl($service->server);
            $data['utilization'] = $serverResult->getUtilization($service->server);

            // Ajout de la récupération des eggs pour le select
            $config = \App\Modules\Pterodactyl\Models\PterodactylConfig::where('product_id', $service->product_id)->first();
            $eggs = [];
            if ($config && isset($config->eggs)) {
                $eggsArray = is_array($config->eggs) ? $config->eggs : json_decode($config->eggs, true);
                if (is_array($eggsArray)) {
                    foreach ($eggsArray as $eggNest) {
                        [$eggId, $nestId] = explode(\App\Modules\Pterodactyl\Models\PterodactylConfig::DELIMITER, $eggNest);
                        $server = $service->server;
                        $eggResponse = \App\Modules\Pterodactyl\Http::callApi($server, "nests/$nestId/eggs/$eggId");
                        if ($eggResponse->status() == 200 && isset($eggResponse->toJson()->attributes->name)) {
                            $eggName = $eggResponse->toJson()->attributes->name;
                            $eggs[$eggId] = $eggName;
                        } else {
                            $eggs[$eggId] = $eggId;
                        }
                    }
                }
            }
            $data['eggs'] = $eggs;
        } catch (ExternalApiException $e) {
            logger()->error($e->getMessage());
            if (in_array('*', $permissions)) {
                \Session::flash('error', $e->getMessage());
            } else {
                \Session::flash('error', __('client.alerts.internalerror'));
            }

            return '';
        }
        $data['uuid'] = $this->uuid;
        $data['offline'] = $this->offline;
        return view($service->type.'::panel/index', $data);
    }

    public function renderAdmin(Service $service)
    {
        return $this->render($service, ['*']);
    }

    public function renderConnect(Service $service)
    {
        $serverResult = PterodactylServerDTO::getServerFromExternalId($service);
        $redirect = $serverResult->autologin($service->server);
        return new RedirectResponse($redirect);
    }
}
