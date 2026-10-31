<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

namespace App\Modules\Pterodactyl;

use App\Exceptions\ExternalApiException;
use App\Models\Provisioning\Server;
use App\Modules\Pterodactyl\Api\PanelApi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Session;

trait PterodactylConfigTrait
{
    use RemembersNonEmpty;

    private function fetchEggs(): array
    {
        return $this->rememberNonEmpty("{$this->type}_config_eggs", now()->addMinutes(10), function () {
            $eggs = [];
            foreach ($this->servers as $server) {
                $eggs = array_replace($eggs, $this->readFromPanel($server, fn () => PanelApi::for($server)->eggs()));
            }

            return $eggs;
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
        return $this->rememberNonEmpty("{$this->type}_config_locations", now()->addMinutes(10), function () {
            $locations = [];
            foreach ($this->servers as $server) {
                foreach ($this->readFromPanel($server, fn () => Http::listAll($server, 'locations')) as $location) {
                    $locations[$location->attributes->id] = $location->attributes->short.' - '.$server->name;
                }
            }

            return $locations;
        });
    }

    // One panel that is down or refuses the key must not empty the lists coming from the other panels.
    private function readFromPanel(Server $server, callable $read): array
    {
        try {
            return $read();
        } catch (ConnectionException $e) {
            Session::flash('error', __('pterodactyl::messages.errors.panel_unreachable', ['server' => $server->name]));
        } catch (ExternalApiException $e) {
            Session::flash('error', __('pterodactyl::messages.errors.panel_refused', ['server' => $server->name]));
        }
        logger()->warning('Pterodactyl panel could not be read', ['server_id' => $server->id, 'error' => $e->getMessage()]);

        return [];
    }
}
