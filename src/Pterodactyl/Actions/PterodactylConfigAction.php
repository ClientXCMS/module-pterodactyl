<?php

namespace App\Pterodactyl\Actions;

use App\Pterodactyl\Database\PterodactylTable;
use ClientX\Actions\ConfigAction;
use ClientX\Services\ConfigActionService as Config;
use ClientX\Renderer\RendererInterface as Renderer;
use ClientX\Router;
use ClientX\Validator;

class PterodactylConfigAction extends ConfigAction
{
    protected array $nullable = ["port_range", "servername", "image", "startup", "db", "backups"];
    protected array $fillable = [
        "memory", "disk", "io",
        "port_range", "swap", "cpu",
        "servername", "egg_id", "nest_id",
        "location_id", "db", "backups",
        "image", "startup"
    ];
    protected string $viewPath = "@pterodactyl_admin/config";
    protected array $types = ["pterodactyl"];

    public function __construct(Router $router, Config $service, Renderer $renderer, PterodactylTable $table)
    {
        parent::__construct($router, $service, $renderer, $table);
    }

    public function validate(array $data): Validator
    {
        $validator = (new Validator($data))

            ->numeric('memory', 'disk', 'io', 'swap', 'cpu', 'egg_id', 'nest_id', 'location_id')
            ->between('io', 9, 9999)
            ->min(-1, "swap")
            ->min(0, "disk", "cpu", "memory")
            ->positive('egg_id', 'nest_id', 'location_id')
            ->notEmpty('memory', 'disk', 'io', 'swap', 'cpu', 'egg_id', 'nest_id', 'location_id');
        if (!empty($data['db'])) {
            $validator->min(0, "db");
        }
        if (!empty($data['backups'])) {
            $validator->min(0, "backups");
        }

        if (!empty($data['servername'])) {
            $validator->length("servername", 1, 191);
        }
        return $validator;
    }
}
