<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

namespace App\Modules\Pterodactyl\Api;

use App\Modules\Pterodactyl\Http;
use App\Modules\Pterodactyl\Models\PterodactylConfig;
use App\Modules\Pterodactyl\PterodactylResponse;

// Pterodactyl 2.x: nests are gone, eggs are addressed directly and grouped by tags.
class TagPanelApi extends PanelApi
{
    public function eggs(): array
    {
        $eggs = [];
        foreach (Http::listAll($this->server, 'eggs', ['include' => 'tags']) as $egg) {
            $tags = collect($egg->attributes->relationships->tags->data ?? [])->pluck('attributes');
            $legacyNest = (int) $tags->pluck('legacy_nest_id')->filter()->first();
            $label = $tags->isEmpty() ? $egg->attributes->name : $egg->attributes->name.' ('.$tags->pluck('name')->join(', ').')';
            $eggs[PterodactylConfig::eggKey($egg->attributes->id, $legacyNest)] = $label;
        }

        return $eggs;
    }

    public function egg(int|string $eggId, int|string|null $nestId, bool $withVariables = false): ?PterodactylResponse
    {
        return Http::callApi($this->server, 'eggs/'.(int) $eggId.($withVariables ? '?include=variables' : ''));
    }

    public function eggAccessProbe(): PterodactylResponse
    {
        return Http::callApi($this->server, 'eggs?per_page=1');
    }

    public function eggResourceName(): string
    {
        return 'eggs';
    }
}
