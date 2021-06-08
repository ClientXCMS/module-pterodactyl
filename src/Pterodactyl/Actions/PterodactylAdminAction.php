<?php
namespace App\Pterodactyl\Actions;

use App\Pterodactyl\Database\PterodactylTable;
use ClientX\Actions\Action;
use ClientX\Renderer\RendererInterface;
use Psr\Http\Message\ServerRequestInterface;

class PterodactylAdminAction extends Action
{

    private PterodactylTable $table;


    public function __construct(RendererInterface $renderer, PterodactylTable $table)
    {
        $this->renderer = $renderer;
        $this->table  = $table;
    }

    public function __invoke(ServerRequestInterface $request)
    {
        $items = $this->table->findAll();
        return $this->render("@pterodactyl_admin/index", compact('items'));
    }
}
