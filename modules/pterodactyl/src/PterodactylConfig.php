<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

namespace App\Modules\Pterodactyl;

use App\Abstracts\AbstractConfig;
use App\Models\Store\Product;
use Illuminate\Support\Facades\Cache;

class PterodactylConfig extends AbstractConfig
{
    protected string $type = 'pterodactyl';

    protected string $model = \App\Modules\Pterodactyl\Models\PterodactylConfig::class;

    use PterodactylConfigTrait;

    protected $fillable = [
        'product_id',
        'memory',
        'disk',
        'io',
        'cpu',
        'node_id',
        'location_id',
        'server_id',
        'backups',
        'image',
        'startup',
        'dedicated_ip',
        'oom_kill',
        'server_name',
        'server_description',
        'swap',
        'port_range',
        'databases',
        'allocations',
        'eggs',
        'force_outgoing_ip',
    ];

    public function render(Product $product)
    {
        $config = $this->getConfig($product->id, new \App\Modules\Pterodactyl\Models\PterodactylConfig);
        // Utiliser fetchEggs pour récupérer les noms réels des eggs
        $eggs = $this->fetchEggs();
        $context = [
            'locations' => $this->fetchLocations(),
            'eggs' => $eggs,
            'delimiter' => \App\Modules\Pterodactyl\Models\PterodactylConfig::DELIMITER,
            'servers' => $this->fetchServers(),
            'product' => $product,
            'config' => $config,
        ];
        $context['currenteggs'] = $config ? (is_array($config->eggs) ? $config->eggs : json_decode($config->eggs, true)) : [];

        return view($this->type.'_admin::product-config', $context);
    }

    public function validate(): array
    {
        return [
            'memory' => 'required|numeric|min:0',
            'disk' => 'required|numeric|min:0',
            'io' => 'required|numeric|min:0',
            'cpu' => 'required|numeric|min:0',
            'location_id' => 'required|numeric',
            'server_id' => 'required|numeric',
            'backups' => 'required|numeric|min:0',
            'image' => 'nullable|string',
            'startup' => 'nullable|string',
            'dedicated_ip' => 'boolean',
            'oom_kill' => 'boolean',
            'server_name' => 'string',
            'server_description' => 'string',
            'swap' => 'numeric|min:0',
            'port_range' => 'nullable|string',
            'databases' => 'numeric|min:0',
            'allocations' => 'numeric|min:0',
            'eggs' => 'array|required',
            'force_outgoing_ip' => 'boolean',
        ];
    }

    public function storeConfig(Product $product, array $parameters)
    {
        $parameters['eggs'] = json_encode($parameters['eggs']);
        $this->model::insert($parameters + ['product_id' => $product->id]);
    }

    public function updateConfig(Product $product, array $parameters)
    {
        $this->clearEggCache($product);

        $parameters['eggs'] = json_encode($parameters['eggs']);
        $this->model::where('product_id', $product->id)->update($parameters);
    }

    /**
     * Clear all pterodactyl cache keys for a given product:
     * - pterodactyl_http_{productId}   (egg names for frontend render)
     * - pterodactyl_eggs_{productId}   (egg list for multi-egg products)
     * - pterodactyl_egg_{serverId}_{eggName} (egg ID/nest mapping per name)
     */
    private function clearEggCache(Product $product): void
    {
        $prefix = $this->type;
        $config = $this->model::where('product_id', $product->id)->first();

        // Invalidate per-egg-name mapping keys before clearing the eggs list
        if ($config !== null && $config->server_id !== null) {
            $cachedEggs = Cache::get("{$prefix}_eggs_{$product->id}");
            if (is_array($cachedEggs)) {
                foreach (array_keys($cachedEggs) as $eggName) {
                    Cache::forget("{$prefix}_egg_{$config->server_id}_{$eggName}");
                }
            }
        }

        Cache::forget("{$prefix}_http_{$product->id}");
        Cache::forget("{$prefix}_eggs_{$product->id}");
    }
}
