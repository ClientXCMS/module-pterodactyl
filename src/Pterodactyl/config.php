<?php

use App\Pterodactyl\Actions\PowerAction;
use App\Pterodactyl\PterodactylType;
use App\Pterodactyl\Panel\PterodactylPanel;
use App\Pterodactyl\PterodactylAdminItem;
use App\Pterodactyl\PterodactylTwigExtension;

use function \DI\get;
use function \DI\add;
use function DI\autowire;

return [
    'servers.types'                             => add(get(\App\Pterodactyl\PterodactylServerType::class)),
    'products.types'                            => add([
        get(PterodactylType::class),
    ]),
     'permissions.list' => add([
        "pterodactyl.admin" => "Pterodactyl configuration",
    ]),
    'twig.extensions' => add(get(PterodactylTwigExtension::class)),
    'panel.list' => add([PterodactylPanel::class => ['pterodactyl']]),
    'admin.menu.items' => add(get(PterodactylAdminItem::class)),
    PowerAction::class => autowire()->constructorParameter('certif', get('app.certificate'))

];
