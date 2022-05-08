<?php
namespace App\Pterodactyl;

use App\Admin\Database\ServerTable;
use App\ClientX\CreateManualServiceInterface;
use App\Pterodactyl\Database\ServersTable;
use App\Shop\Entity\Product;
use App\Shop\Entity\Service;
use App\Pterodactyl\Database\PterodactylTable;
use ClientX\Database\NoRecordException;
use ClientX\Renderer\RendererInterface;
use ClientX\Validator;

class PterodactylManualService implements CreateManualServiceInterface {

    private ServersTable $serversTable;
    private PterodactylTable $pterodactylTable;
    private ServerTable $serverTable;
    public function __construct(ServersTable $serversTable, PterodactylTable $pterodactylTable, ServerTable $serverTable)
    {
        $this->serversTable = $serversTable;
        $this->pterodactylTable = $pterodactylTable;
        $this->serverTable = $serverTable;
    }
    public function type():string{

        return PterodactylServerType::class;
    }
    public function import(Service $service, Product $product, array $data):string{
        $id = $data['id'];

        $config = $this->pterodactylTable->findConfig($product->getId());
        if ($config->serverId == null){
            $server = $this->serverTable->findFirst(['pterodactyl']);
        } else {
            $server = $this->serverTable->find($config->serverId);
        }
        $serverData = Http::callApi($server, 'servers/' . $id)->data();

        $response = Http::callApi($server, "servers/$id/details",
        [
            'name' => $serverData->attributes->name,
            'description' => $serverData->attributes->description,
            'external_id' => (string)$service->getId(),
            'user' => $serverData->attributes->user
        ]
        , "PATCH");
        $this->serversTable->saveServer($service->getId(),$server->getId(), $product->getId());
        return 'success';
    }
    public function render(RendererInterface $renderer, array $data):string{
        try {
            $config = $this->pterodactylTable->findConfig($data['product']->getId());
            if ($config->serverId == null){
                $server = $this->serverTable->findFirst(['pterodactyl']);
            } else {
                $server = $this->serverTable->find($config->serverId);
            }
        } catch (NoRecordException $e){
            return 'Config not found';
        }

        $servers = Http::callApi($server, "servers?page=0&per_page=10000")->data()->data;
        $ids = @$this->serversTable->makeQuery()->select('server_id as id')->fetchAll()->getIds();
        $serverNames = collect($servers)->filter(function($server) use ($ids){
            return !in_array($server->attributes->id, $ids);
        })->mapWithKeys(function($server){
            return [$server->attributes->id => $server->attributes->name];
        })->toArray();

        return $renderer->render("@pterodactyl_admin/manual", ['serverNames' => $serverNames]);
    }
    public function validate(array $data):Validator{
        return new Validator($data);
    }
    public function params(array $data):array{

        return $data;
    }
} 