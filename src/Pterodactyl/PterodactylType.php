<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

namespace App\Pterodactyl;

use App\Admin\Database\ServerTable;
use App\Pterodactyl\Actions\PterodactylConfigAction;
use App\Pterodactyl\Database\PterodactylTable;
use ClientX\Database\NoRecordException;
use ClientX\Product\ProductTypeInterface;
use ClientX\Router;
use function ClientX\request;

class PterodactylType implements ProductTypeInterface
{
    /**
     * @var \App\Pterodactyl\Database\PterodactylTable
     */
    private PterodactylTable $table;
    /**
     * @var \ClientX\Router
     */
    private Router $router;
    /**
     * @var \App\Admin\Database\ServerTable
     */
    private ServerTable $serverTable;

    public function __construct(PterodactylTable $table, Router $router, ServerTable $serverTable)
    {
        $this->table = $table;
        $this->router = $router;
        $this->serverTable = $serverTable;
    }

    public function getName(): string
    {
        return "pterodactyl";
    }

    public function getTitle(): string
    {
        return "Pterodactyl";
    }

    public function getConfigPath(): string
    {
        return "pterodactyl.config";
    }

    public function getData(): ?string
    {
        $url = request()->getUri()->getPath();
        if ($url == '/basket' || str_starts_with($url, '/basket') || str_starts_with($url, '/store')) {
            $id = 0;
            $match = $this->router->match(request());
            if ($match->getParams()['id'] ?? null) {
                try {
                    $config = $this->table->findConfig($match->getParams()['id']);
                    $eggsAndNest = json_decode($config->eggs, true);
                    if (count($eggsAndNest) == 1) {
                        return null;
                    }
                } catch (NoRecordException $e) {
                    return PterodactylData::class;
                }
            }else{
                return null;
            }
        }
        return PterodactylData::class;
    }


    private function getEggs(array $eggsAndNest, ?int $serverId = null): array
    {
        $eggs = [];
        foreach ($eggsAndNest as $value) {
            [$egg, $nest] = explode(PterodactylConfigAction::DELIMITER, $value);
            
            if ($serverId) {
                $server = $this->serverTable->find($serverId);
            } else {
                $server = $this->serverTable->findFirst(['pterodactyl']);
            }
            $response = Http::callApi($server, "nests/$nest/eggs/$egg?include=variables");
            if ($response->status() == 200) {
                $response = $response->data();
                $eggs[$response->attributes->name] = $response->attributes->name;
            }
        }
        return $eggs;
    }
}
