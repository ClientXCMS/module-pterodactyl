<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

namespace App\Modules\Pterodactyl;

use Illuminate\Support\Facades\Cache;

trait RemembersNonEmpty
{
    // Cache::remember would also keep an empty answer from a panel that was down or misconfigured at that moment.
    private function rememberNonEmpty(string $key, ?\DateTimeInterface $expiresAt, callable $compute): mixed
    {
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }
        $value = $compute();
        if (! empty($value)) {
            $expiresAt === null ? Cache::forever($key, $value) : Cache::put($key, $value, $expiresAt);
        }

        return $value;
    }
}
