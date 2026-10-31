<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Module 1.4 cached egg lookups forever, empty ones included; 1.5 reads other keys, so these entries would never expire.
return new class extends Migration
{
    public function up(): void
    {
        Cache::forget('pterodactyl_eggs');
        Cache::forget('pterodactyl_locations');
        Cache::forget('pterodactyl_servers');
        DB::table('pterodactyl_configs')->select(['id', 'product_id', 'server_id'])->orderBy('id')->each(function ($config) {
            $names = Cache::get("pterodactyl_eggs_{$config->product_id}");
            foreach (is_array($names) ? array_keys($names) : [] as $name) {
                Cache::forget("pterodactyl_egg_{$config->server_id}_{$name}");
            }
            Cache::forget("pterodactyl_eggs_{$config->product_id}");
            Cache::forget("pterodactyl_http_{$config->product_id}");
        });
    }

    public function down(): void {}
};
