<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

namespace App\Modules\Pterodactyl;

use App\DTO\Store\ProductDataDTO;
use App\Models\Provisioning\Server;
use App\Models\Provisioning\SubdomainHost;
use App\Modules\Pterodactyl\Api\PanelApi;
use App\Modules\Pterodactyl\Models\PterodactylConfig;
use App\Rules\DomainIsNotRegisted;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;

class PterodactylData extends \App\Abstracts\AbstractProductData
{
    use RemembersNonEmpty;

    // Bumped when the cached shape or its source changes, so a deployment never serves entries computed by the previous code.
    const CACHE_PREFIX = 'pterodactyl_cache2';

    protected array $parameters = ['eggname', 'egg_id', 'nest_id'];

    protected string $namespace = 'pterodactyl';

    public function primary(ProductDataDTO $productDataDTO): string
    {
        return $productDataDTO->data['eggname'] ?? '';
    }

    public function render(ProductDataDTO $productDataDTO)
    {
        if ($productDataDTO->product == null || $productDataDTO->product->id == null) {
            return 'Product not found';
        }
        $config = $this->getConfig($productDataDTO->product->id);
        if ($config == null) {
            return __('provisioning.product_not_configured');
        }
        $productId = $productDataDTO->product->id;
        $eggs = $this->rememberNonEmpty(self::CACHE_PREFIX."_http_{$productId}", null, function () use ($config, $productId) {
            $names = $this->getEggs($config->eggs, $config->server_id, $productId);

            return $names === [] ? null : ['eggnames' => $names, 'defaultEgg' => $names[array_key_first($names)]];
        }) ?? ['eggnames' => [], 'defaultEgg' => ''];

        $data['eggnames'] = $eggs['eggnames'];
        $data['eggname'] = $productDataDTO->data['eggname'] ?? $eggs['defaultEgg'];
        if (app('extension')->extensionIsEnabled('cloudflaresubdomains')) {
            $data['subdomains'] = SubdomainHost::getItemsByMetadata('cloudflare_zone_id');
        } else {
            $data['subdomains'] = collect();
        }

        return view($this->namespace.'::product-data', $data)->render();
    }

    public function renderAdmin(ProductDataDTO $productDataDTO)
    {
        $this->namespace = 'pterodactyl_admin';

        return $this->render($productDataDTO);
    }

    public function validate(): array
    {
        if (! app('extension')->extensionIsEnabled('cloudflaresubdomains') || SubdomainHost::count() == 0) {
            return ['eggname' => ['string']];
        }

        return [
            'eggname' => ['string'],
            'domain_subdomain' => ['nullable', 'string', 'max:255', new DomainIsNotRegisted(true), new RequiredIf(function () {
                return request()->input('domain') == null && SubdomainHost::count() > 0;
            })],
            'subdomain' => ['nullable', 'string', 'max:255', Rule::in(SubdomainHost::all()->pluck('domain')->toArray()), new RequiredIf(function () {
                return request()->input('domain') == null && SubdomainHost::count() > 0;
            })],
        ];
    }

    public function parameters(ProductDataDTO $productDataDTO): array
    {
        $eggname = $productDataDTO->parameters['eggname'] ?? null;
        $config = $this->getConfig($productDataDTO->product->id);

        if ($config == null) {
            return [
                'error' => 'Please configure your product data in the admin panel',
            ];
        }
        if ($config->server_id == null) {
            return [
                'error' => 'Please configure your product data in the admin panel',
            ];
        }

        if ($eggname == null) {
            [$egg, $nest] = PterodactylConfig::parseEggKey((string) current($config->eggs));
            $server = Server::find($config->server_id);
            $response = $server === null ? null : PanelApi::for($server)->egg($egg, $nest);
            $eggname = $response?->status() == 200 ? $response->toJson()->attributes->name : 'Default';
        } else {
            [$egg, $nest] = $this->getEgg($config->eggs, $eggname, $config->server_id);

            if ($egg === null) {
                return [
                    'error' => 'Please clear cache in extension page',
                ];
            }
        }
        if (request()->input('domain_subdomain') != null) {
            $domain = strtolower(request()->input('domain_subdomain').request()->input('subdomain'));
        } else {
            $domain = null;
        }

        return [
            'eggId' => $egg,
            'nestId' => $nest,
            'eggname' => $eggname,
            'domain_subdomain' => $domain,
        ];
    }

    public function getEggs(array $eggsAndNest, int $serverId, int $productId): array
    {
        return $this->rememberNonEmpty(self::CACHE_PREFIX."_eggs_{$productId}", null, function () use ($eggsAndNest, $serverId) {
            $eggs = [];
            foreach ($this->resolveEggs($eggsAndNest, $serverId) as $attributes) {
                $eggs[$attributes->name] = $attributes->name;
            }

            return $eggs;
        });
    }

    public function getEgg(array $eggsAndNest, string $eggname, int $serverId): array
    {
        return $this->rememberNonEmpty(self::CACHE_PREFIX."_egg_{$serverId}_{$eggname}", null, function () use ($eggsAndNest, $eggname, $serverId) {
            foreach ($this->resolveEggs($eggsAndNest, $serverId) as $key => $attributes) {
                if ($attributes->name === $eggname) {
                    return PterodactylConfig::parseEggKey($key);
                }
            }

            return null;
        }) ?? [null, null];
    }

    // Saved key => egg attributes, for the eggs the panel still knows.
    private function resolveEggs(array $eggsAndNest, int $serverId): array
    {
        $server = Server::find($serverId);
        if ($server === null) {
            return [];
        }
        $api = PanelApi::for($server);
        $resolved = [];
        foreach ($eggsAndNest as $key) {
            [$egg, $nest] = PterodactylConfig::parseEggKey((string) $key);
            $response = $api->egg($egg, $nest);
            if ($response?->status() == 200 && isset($response->toJson()->attributes->name)) {
                $resolved[$key] = $response->toJson()->attributes;
            }
        }

        return $resolved;
    }

    protected function getConfig(int $productId)
    {
        return PterodactylConfig::where('product_id', $productId)->first();
    }
}
