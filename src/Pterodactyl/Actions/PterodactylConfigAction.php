<?php

namespace App\Pterodactyl\Actions;

use App\Pterodactyl\Database\PterodactylTable;
use App\Admin\Entity\Server;
use App\Admin\Database\ServerTable;
use App\Pterodactyl\Http;
use App\Pterodactyl\PterodactylTrait;
use ClientX\Actions\ConfigAction;
use ClientX\Services\ConfigActionService as Config;
use ClientX\Renderer\RendererInterface as Renderer;
use ClientX\Router;
use ClientX\Validator;

class PterodactylConfigAction extends ConfigAction
{
    protected array $fillable = [
        "memory", "disk", "io",
        "port_range", "swap", "cpu",
        "servername", "eggs",
        "location_id", "db", "backups",
        "image", "startup",
    ];
    protected string $viewPath = "@pterodactyl_admin/config";
    protected array $types = ["pterodactyl"];

    const DELIMITER = "---------";
    private array $servers;
    private PterodactylTable $table;

    use PterodactylTrait;

    /**
     * PterodactylConfigAction constructor.
     * @param \ClientX\Router $router
     * @param \ClientX\Services\ConfigActionService $service
     * @param \ClientX\Renderer\RendererInterface $renderer
     * @param \App\Pterodactyl\Database\PterodactylTable $table
     * @param \App\Admin\Database\ServerTable $serverTable
     */
    public function __construct(
        Router $router,
        Config $service,
        Renderer $renderer,
        PterodactylTable $table,
        ServerTable $serverTable
    ) {
        parent::__construct($router, $service, $renderer, $table);
        $servers = $serverTable->findIn($this->types, 'type')->fetchAll();
        $this->servers = collect($servers)->filter(function (Server $server) {
            return $server->isHidden() == false;
        })->toArray();
        $this->table = $table;
        $this->flash = $service->getFlash();
    }

    public function validate(array $data): Validator
    {

        $validator = (new Validator($data))
            ->between('io', 9, 9999)
            ->min(-1, "swap")
            ->min(-1, "disk", "cpu", "memory")
            ->notEmpty('io', 'location_id');
        if (!empty($data['db'])) {
            $validator->min(0, "db");
        }
        if (!empty($data['backups'])) {
            $validator->min(0, "backups");
        }

        if (isset($data['eggs'][0]) == false){
            $validator->notEmpty('eggs[]');
        }

        if (!empty($data['servername'])) {
            $validator->length("servername", 1, 191);
        }
        return $validator;
    }

    protected function formParams(array $params)
    {
        $params = array_merge($params, $this->callApi());
        $params['DELIMITER'] = self::DELIMITER;
        return parent::formParams($params);
    }

    private function callApi(): array
    {
        $nests = collect($this->servers)->map(function (Server $server) {
            $response = Http::callApi($server, 'nests')->data();
            if (property_exists($response, 'data')) {
                $data = $response->data;
            } else {
                $this->service->getFlash()->error($server->getName() . ' : Nests or locations cannot be reached (check your application key permission)');
                $data = [];
            }
            return collect($data)->mapWithKeys(function ($data, $id) use ($server) {
                $attr = $data->attributes;
                $nestId = $attr->id;
                $nest = $attr;
                $data = Http::callApi($server, "nests/$nestId/eggs")->data()->data;
                $eggs = collect($data)->mapWithKeys(function ($data, $id) use ($nest) {

                    $attr = $data->attributes;
                    return [join(self::DELIMITER, [$attr->id, $nest->id]) => $attr->name . " (" . $nest->name . ")"];
                })->toArray();
                return [$id => [
                    'serverId' => $server->getId(),
                    'nestId' => $nestId,
                    'eggs' => $eggs
                ]];
            })->toArray();
        })->toArray();
        $locations = collect($this->servers)->mapWithKeys(function (Server $server) {
            $response = Http::callApi($server, 'locations')->data();
            if (property_exists($response, 'data')) {
                $data = $response->data;
            } else {
                $this->service->getFlash()->error($server->getName() . ' : Nests or locations cannot be reached (check your application key permission)');
                $data = [];
            }
            return collect($data)->mapWithKeys(function ($data) use ($server) {
                $attr = $data->attributes;
                return [$attr->id => $attr->short];
            })->toArray();
        })->toArray();
        $eggs = collect($nests)->mapWithKeys(function (array $nest) {
            return collect($nest)->mapWithKeys(function ($nest) {
                return $nest['eggs'];
            })->toArray();
        })->toArray();
        return compact('locations', 'eggs');
    }
}
