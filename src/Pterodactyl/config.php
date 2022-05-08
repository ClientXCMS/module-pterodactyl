<?php

use App\Pterodactyl\Actions\PowerAction;
use App\Pterodactyl\PterodactylType;
use App\Pterodactyl\Panel\PterodactylPanel;
use App\Pterodactyl\PterodactylTwigExtension;
use App\Pterodactyl\PterodactylManualService;
use function \DI\get;
use function \DI\add;
use function DI\autowire;

return [
    'servers.types'                             => add(get(\App\Pterodactyl\PterodactylServerType::class)),
    'products.types'                            => add([
        get(PterodactylType::class),
    ]),
     'permissions.list' => add([
        "pterodactyl.admin" => "Pterodactyl index",
        "pterodactyl.admin.config" => "Pterodactyl config",
    ]),
    'twig.extensions' => add(get(PterodactylTwigExtension::class)),
    'panel.list' => add([PterodactylPanel::class => ['pterodactyl']]),
    PowerAction::class => autowire()->constructorParameter('certif', get('app.certificate')),
    'manualservice.list' => add(get(PterodactylManualService::class))
];
