<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

namespace App\Modules\Pterodactyl\Api;

use App\Models\Provisioning\Server;
use App\Modules\Pterodactyl\Http;
use App\Modules\Pterodactyl\PterodactylResponse;
use Illuminate\Support\Facades\Cache;

abstract class PanelApi
{
    private const CACHE_MINUTES = 10;

    public function __construct(protected readonly Server $server) {}

    // 2.x answers GET tags, 1.x has no such route (404); Wisp exposes the 1.x nests API and is never probed.
    public static function for(Server $server): self
    {
        if ($server->type === 'wisp') {
            return new NestPanelApi($server);
        }
        $key = self::cacheKey($server);
        $known = $key === null ? null : Cache::get($key);
        if ($known === 'tags' || $known === 'nests') {
            return self::make($known, $server);
        }
        $status = Http::callApi($server, 'tags?per_page=1')->status();
        $detected = match (true) {
            $status >= 200 && $status < 300 => 'tags',
            $status === 404 => 'nests',
            default => null,
        };
        if ($detected === null) {
            return new NestPanelApi($server);
        }
        if ($key !== null) {
            Cache::put($key, $detected, now()->addMinutes(self::CACHE_MINUTES));
        }

        return self::make($detected, $server);
    }

    // Labels keyed "<egg><DELIMITER><nest>", the format product configurations are saved with.
    abstract public function eggs(): array;

    // Null when the egg cannot be addressed on this panel (a 1.x egg without its nest).
    abstract public function egg(int|string $eggId, int|string|null $nestId, bool $withVariables = false): ?PterodactylResponse;

    abstract public function eggAccessProbe(): PterodactylResponse;

    abstract public function eggResourceName(): string;

    private static function make(string $kind, Server $server): self
    {
        return $kind === 'tags' ? new TagPanelApi($server) : new NestPanelApi($server);
    }

    private static function cacheKey(Server $server): ?string
    {
        return $server->id === null ? null : 'pterodactyl_panel_api_'.$server->id.'_'.sha1($server->hostname.':'.$server->port);
    }
}
