<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

namespace App\Modules\Pterodactyl\Controllers;

use App\Models\Provisioning\Service;
use App\Modules\Pterodactyl\DTO\PterodactylServerDTO;

class PterodactylController extends \App\Http\Controllers\Controller
{
    public function changeEgg(Service $service)
    {
        if (!auth('admin')->check()) {
            if (auth('web')->guest()) {
                abort(404);
            }
            if (!auth('web')->user()->hasServicePermission($service, 'pterodactyl.changeegg')) {
                abort(404);
            }
        }
        abort_if($service->type != 'pterodactyl', 404);

        $server = \App\Modules\Pterodactyl\DTO\PterodactylServerDTO::getServerFromExternalId($service);
        if ($server->suspended()) {
            \Session::flash('error', __('client.alerts.service_suspended'));
            return redirect()->route('front.services.show', $service);
        }
        if (!$server->installed()) {
            \Session::flash('error', __('client.alerts.servernotinstalled'));
            return redirect()->route('front.services.show', $service);
        }

        $eggId = request('egg_id');
        if (empty($eggId)) {
            $eggId = $server->attributes->egg ?? ($server->attributes->container->egg_id ?? null);
        }

        try {
            $server->changeEgg($service, $eggId);
            \Session::flash('success', __('pterodactyl::panel.reinstall_success'));
        } catch (\App\Exceptions\ExternalApiException $e) {
            \Session::flash('error', $e->getMessage());
        }

        return redirect()->back();
    }

    public function power(Service $service, string $power)
    {
        if (! auth('admin')->check()) {
            if (auth('web')->guest()) {
                abort(404);
            }
            if (! auth('web')->user()->hasServicePermission($service, 'pterodactyl.power')) {
                abort(404);
            }
        }
        abort_if(! in_array($power, ['start', 'stop', 'restart']), 404);
        abort_if($service->type != 'pterodactyl', 404);
        $server = PterodactylServerDTO::getServerFromExternalId($service);
        if ($server->suspended()) {
            \Session::flash('error', __('client.alerts.service_suspended'));

            return redirect()->route('front.services.show', $service);
        }
        if (! $server->installed()) {
            \Session::flash('error', __('client.alerts.servernotinstalled'));

            return redirect()->route('front.services.show', $service);
        }
        $result = $server->power($service, $power);
        if ($result->status() == 204) {
            \Session::flash('success', __('client.alerts.power_success'));
        } else {
            logger()->error($result->toJson()->errors[0]->detail);
            \Session::flash('error', __('client.alerts.power_error'));
        }

        return redirect()->back();
    }
}
