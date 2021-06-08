<?php
namespace App\Pterodactyl;

use App\Pterodactyl\Actions\PowerAction;
use App\Pterodactyl\Actions\PterodactylAdminAction;
use App\Pterodactyl\Actions\PterodactylConfigAction;
use ClientX\Module;
use ClientX\Renderer\RendererInterface;
use ClientX\Router;
use ClientX\Theme\ThemeInterface;
use Psr\Container\ContainerInterface;

class PterodactylModule extends Module
{

    const DEFINITIONS = __DIR__ . '/config.php';
    const MIGRATIONS = __DIR__ . '/db/migrations';
    const TRANSLATIONS = [
        "fr_FR" => __DIR__ . '/trans/fr.php',
        "en_GB" => __DIR__ . '/trans/en.php',
    ];
    public function __construct(ContainerInterface $container, ThemeInterface $theme, RendererInterface $renderer, Router $router)
    {
        $renderer->addPath('pterodactyl', $theme->getViewsPath() . '/Pterodactyl');
        $renderer->addPath('pterodactyl_admin', __DIR__ . '/Views');
        
       $prefix = $container->get("clientarea.prefix");
       $router->get("$prefix/pterodactyl/[*:power]/[i:id]", PowerAction::class, 'pterodactyl.power');
        /** @var Router */
        if ($container->has('admin.prefix')) {
            $prefix = $container->get('admin.prefix') . '/pterodactyl';
            $router->get("$prefix", PterodactylAdminAction::class, 'pterodactyl.admin');
            $router->any("$prefix/config/[i:id]", PterodactylConfigAction::class, "pterodactyl.config");
        }
    }
}
