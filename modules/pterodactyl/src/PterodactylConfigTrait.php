<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

namespace App\Modules\Pterodactyl;

use App\Models\Provisioning\Server;
use Illuminate\Support\Facades\Cache;
use Session;

trait PterodactylConfigTrait
{
    private function fetchEggs()
    {
        return Cache::rememberForever('pterodactyl_eggs', function () {
            $tmp = [];
            foreach ($this->servers as $server) {
                $response = Http::callApi($server, 'nests')->toJson();
                if ($response == null) {
                    Session::flash('error', $server->name.' : Error PHPMyAdmin detected. Please use domain name.');

                    continue;
                }
                if (property_exists($response, 'data')) {
                    $data = $response->data;
                } else {
                    Session::flash('error', $server->name.' : Nests or locations cannot be reached (check your application key permission)');

                    continue;
                }
                foreach ($data as $element) {
                    $attr = $element->attributes;
                    $nestId = $attr->id;
                    $nest = $attr;
                    $request = Http::callApi($server, "nests/$nestId/eggs");
                    if ($request->status() != 200) {
                        Session::flash('error', $server->name.' : Nests '.$nestId.' cannot be reached (check your application key permission) Statut code : '.$request->status());

                        continue;
                    }
                    $eggs = $request->toJson()->data;
                    foreach ($eggs as $egg) {
                        $tmp[implode(\App\Modules\Pterodactyl\Models\PterodactylConfig::DELIMITER, [$egg->attributes->id, $nest->id])] = $egg->attributes->name.' ('.$nest->name.')';
                    }
                }
            }
            if (empty($tmp)) {
                Cache::forget('pterodactyl_eggs');
            }

            return $tmp;
        });
    }

    private function fetchServers()
    {
        return Cache::remember('pterodactyl_servers', now()->addMinutes(10), function () {
            return collect($this->servers)->mapWithKeys(function (Server $server) {
                return [$server->id => $server->name];
            })->toArray();
        });
    }

    private function fetchLocations(): array
    {
        return Cache::rememberForever('pterodactyl_locations', function () {
            return collect($this->servers)->mapWithKeys(function (Server $server) {
                $response = Http::callApi($server, 'locations')->toJson();
                if ($response == null) {
                    Session::flash('error', $server->name.' : Error PHPMyAdmin detected. Please use domain name.');

                    return [];
                }
                if (property_exists($response, 'data')) {
                    $data = $response->data;
                } else {
                    Session::flash('error', $server->name.' : Nests or locations cannot be reached (check your application key permission)');
                    $data = [];
                }

                return collect($data)->mapWithKeys(function ($data) use ($server) {
                    $attr = $data->attributes;

                    return [$attr->id => $attr->short.' - '.$server->name];
                })->toArray();
            })->toArray();
        });
    }
}
