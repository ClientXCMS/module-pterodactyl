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
use App\Modules\Pterodactyl\Models\PterodactylConfig;
use Illuminate\Support\Facades\Cache;

class PterodactylData extends \App\Abstracts\AbstractProductData
{
    protected array $parameters = ['eggname', 'egg_id', 'nest_id'];

    public function primary(ProductDataDTO $productDataDTO): string
    {
        return $productDataDTO->data['eggname'] ?? '';
    }

    public function render(ProductDataDTO $productDataDTO)
    {
        $config = $this->getConfig($productDataDTO->product->id);
        if ($config == null) {
            return __('provisioning.product_not_configured');
        }

        return Cache::rememberForever("pterodactyl_render_{$productDataDTO->product->id}", function () use ($config, $productDataDTO) {
            $eggs = $config->eggs;
            if (count($eggs) == 1) {
                [$egg, $nest] = explode(PterodactylConfig::DELIMITER, $eggs[0]);
                $result = Http::callApi(Server::find($config->server_id), "nests/$nest/eggs/$egg");
                if ($result->status() == 200) {
                    return 'Eggs : ' . $result->toJson()->attributes->name;
                }
                return 'Default egg : Not found';
            }
            $eggs = $this->getEggs($eggs, $config->server_id, $productDataDTO->product->id);
            $data['eggnames'] = $eggs;
            $data['eggname'] = $productDataDTO->data['eggname'] ?? '';
            return view($productDataDTO->product->type . '::product-data', $data)->render();
        });
    }

    public function validate(): array
    {
        return ['eggname' => ['string']];
    }

    public function parameters(ProductDataDTO $productDataDTO): array
    {
        return Cache::rememberForever("pterodactyl_parameters_{$productDataDTO->product->id}", function () use ($productDataDTO) {
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
                    $eggname = "Default";
                }
            } else {
                $eggs = $config->eggs;
                [$egg, $nest] = $this->getEgg($eggs, $eggname, $config->server_id);
            }

            return [
                'eggId' => $egg,
                'nestId' => $nest,
                'eggname' => $eggname,
            ];
        });
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
                    throw new \Exception($server->name . ' : Egg '. $egg .' cannot be reached (check your application key permission) Statut code : ' . $response->status());
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
                    throw new \Exception($server->name . ' : Egg '. $egg .' cannot be reached (check your application key permission) Statut code : ' . $response->status());
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
