<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

namespace App\Modules\Pterodactyl\Api;

use App\Exceptions\ExternalApiException;
use App\Modules\Pterodactyl\Http;
use App\Modules\Pterodactyl\Models\PterodactylConfig;
use App\Modules\Pterodactyl\PterodactylResponse;

// Pterodactyl 1.x and Wisp: eggs live under their nest.
class NestPanelApi extends PanelApi
{
    public function eggs(): array
    {
        try {
            return $this->eggsByNest();
        } catch (ExternalApiException $e) {
            return $this->refreshed()?->eggs() ?? throw $e;
        }
    }

    private function eggsByNest(): array
    {
        $eggs = [];
        foreach (Http::listAll($this->server, 'nests') as $nest) {
            $nestId = (int) $nest->attributes->id;
            foreach (Http::listAll($this->server, "nests/{$nestId}/eggs") as $egg) {
                $eggs[PterodactylConfig::eggKey($egg->attributes->id, $nestId)] = $egg->attributes->name.' ('.$nest->attributes->name.')';
            }
        }

        return $eggs;
    }

    public function egg(int|string $eggId, int|string|null $nestId, bool $withVariables = false): ?PterodactylResponse
    {
        if ((int) $nestId <= 0) {
            return $this->refreshed()?->egg($eggId, $nestId, $withVariables);
        }
        $response = Http::callApi($this->server, 'nests/'.(int) $nestId.'/eggs/'.(int) $eggId.($withVariables ? '?include=variables' : ''));
        if ($response->status() === 404) {
            return $this->refreshed()?->egg($eggId, $nestId, $withVariables) ?? $response;
        }

        return $response;
    }

    public function eggAccessProbe(): PterodactylResponse
    {
        return Http::callApi($this->server, 'nests');
    }

    public function eggResourceName(): string
    {
        return 'nests';
    }
}
