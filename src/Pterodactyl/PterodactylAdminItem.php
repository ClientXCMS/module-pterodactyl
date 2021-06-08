<?php
namespace App\Pterodactyl;

use ClientX\Navigation\NavigationItemInterface;
use ClientX\Renderer\RendererInterface;

class PterodactylAdminItem implements NavigationItemInterface
{

    public function render(RendererInterface $renderer): string
    {
        return $renderer->render("@pterodactyl_admin/menu");
    }

    public function getPosition(): int
    {
        return 80;
    }
}
