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
use App\Modules\Pterodactyl\Models\PterodactylConfig;
use App\Rules\DomainIsNotRegisted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;

class PterodactylData extends \App\Abstracts\AbstractProductData
{
    protected array $parameters = ['eggname', 'egg_id', 'nest_id'];

    protected string $namespace = 'pterodactyl';

    public function primary(ProductDataDTO $productDataDTO): string
    {
        return $productDataDTO->data['eggname'] ?? '';
    }

    public function render(ProductDataDTO $productDataDTO)
    {
        if ($productDataDTO->product == null) {
            return 'Product not found';
        }
        $config = $this->getConfig($productDataDTO->product->id);
        if ($config == null) {
            return __('provisioning.product_not_configured');
        }
        $eggs = Cache::rememberForever("pterodactyl_http_{$productDataDTO->product->id}", function () use ($productDataDTO, $config) {
            if (count($config->eggs) == 1) {
                [$egg, $nest] = explode(PterodactylConfig::DELIMITER, $config->eggs[0]);
                $result = Http::callApi(Server::find($config->server_id), "nests/$nest/eggs/$egg");
                if ($result->status() == 200) {
                    return [
                        'eggnames' => [$result->toJson()->attributes->name => $result->toJson()->attributes->name],
                        'defaultEgg' => $result->toJson()->attributes->name,
                    ];
                }

                return [
                    'eggnames' => [],
                    'defaultEgg' => 'Not found',
                ];
            }
            $eggs = $this->getEggs($config->eggs, $config->server_id, $productDataDTO->product->id);

            return [
                'eggnames' => $eggs,
                'defaultEgg' => $eggs[array_key_first($eggs)] ?? '',
            ];
        });

        $data['eggnames'] = $eggs['eggnames'];
        $data['eggname'] = $productDataDTO->data['eggname'] ?? $eggs['defaultEgg'];
        if (app('extension')->extensionIsEnabled('cloudflaresubdomains')){
            $data['subdomains'] = SubdomainHost::getItemsByMetadata("cloudflare_zone_id");
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
        if (!app('extension')->extensionIsEnabled('cloudflaresubdomains') || SubdomainHost::count() == 0) {
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
            $server = Server::find($config->server_id);
            [$egg, $nest] = explode(PterodactylConfig::DELIMITER, current($config->eggs));
            $request = Http::callApi($server, "nests/$nest/eggs/$egg?include=variables");
            if ($request->status() == 200) {
                $eggname = $request->toJson()->attributes->name;
            } else {
                $eggname = 'Default';
            }
        } else {
            $eggs = $config->eggs;
            [$egg, $nest] = $this->getEgg($eggs, $eggname, $config->server_id);
        }
        if (request()->input('domain_subdomain') != null) {
            $domain = strtolower(request()->input('domain_subdomain') . request()->input('subdomain'));
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
        return Cache::rememberForever("pterodactyl_eggs_$productId", function () use ($eggsAndNest, $serverId) {
            $eggs = [];
            foreach ($eggsAndNest as $value) {
                [$egg, $nest] = explode(PterodactylConfig::DELIMITER, $value);
                $server = Server::find($serverId);
                $response = Http::callApi($server, "nests/$nest/eggs/$egg?include=variables");
                if ($response->status() == 200) {
                    $response = $response->toJson();
                    $eggs[$response->attributes->name] = $response->attributes->name;
                } else {
                    //throw new \Exception($server->name.' : Egg '.$egg.' cannot be reached (check your application key permission) Statut code : '.$response->status());
                }
            }

            return $eggs;
        });
    }

    public function getEgg(array $eggsAndNest, string $eggname, int $serverId): array
    {
        return Cache::rememberForever("pterodactyl_egg_{$serverId}_{$eggname}", function () use ($eggsAndNest, $eggname, $serverId) {
            foreach ($eggsAndNest as $value) {
                [$egg, $nest] = explode(PterodactylConfig::DELIMITER, $value);
                $server = Server::find($serverId);

                $response = Http::callApi($server, "nests/$nest/eggs/$egg");
                if ($response->status() == 200) {
                    $response = $response->toJson();
                    if ($response->attributes->name === $eggname) {
                        return [$egg, $nest];
                    }
                } else {
                    //throw new \Exception($server->name.' : Egg '.$egg.' cannot be reached (check your application key permission) Statut code : '.$response->status());
                }
            }

            return [null, null];
        });
    }

    protected function getConfig(int $productId)
    {
        return PterodactylConfig::where('product_id', $productId)->first();
    }
}
