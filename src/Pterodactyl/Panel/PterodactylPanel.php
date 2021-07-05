<?php
namespace App\Pterodactyl\Panel;

use App\Pterodactyl\Http;
use App\Shop\Entity\Service;
use App\Shop\Panel\PanelInterface;
use ClientX\Renderer\RendererInterface;
class PterodactylPanel implements PanelInterface
{
    const STATS = [
        "memory" => ["secondary", "fas fa-atom"],
        "swap" => ["info", "fas fa-memory", "used"],
        "disk" => ["success", "fas fa-microchip"],
        "io" => ["danger", "fas fa-hdd"],
        "cpu" => ["dark", "fas fa-hdd"]
    ];
    
    public function render(RendererInterface $renderer, Service $service): string
    {
        $data = [];
        $serverResult = Http::callApi($service->server, 'servers/external/' . $service->getId() . "?include=allocations,utilization");
        if (!$serverResult->successful()) {
            $data['errors'] = "Server not found";
            return $renderer->render("@pterodactyl/panel", compact('data'));
        }
        $attributes = $serverResult->data()->attributes;

        $utilizationResult = Http::callApi($service->server, 'servers/' . $attributes->identifier . "/resources", [], 'GET', true, 'client');

        

        if (!$attributes->container->installed) {
            $data['errors'] = "Server not installed";
        }
        $schema = $service->server->isSecure() ? 'https://' : 'http://';
        $ip = $service->server->getIpaddress();
        $data['href'] =  sprintf('%s%s/server/%s', $schema, $ip, $attributes->identifier);
    
        if ($attributes->suspended) {
            $data['errors'] = "Server suspended";
        }
        $data['utilization'] = $utilizationResult->data()->attributes;
        $data['attributes'] = $attributes;
        $data['service'] = $service;
        $data['ips'] = collect($attributes->relationships->allocations->data)->map(function($data){
            return $data->attributes->alias ? $data->attributes->alias : $data->attributes->ip  . ":" . $data->attributes->port;
        })->join(',');
        $data['inAdmin'] = false;
        $data['stats'] = self::STATS;
        return $renderer->render("@pterodactyl/panel", $data);
    }

    public function renderAdmin(RendererInterface $renderer, Service $service): string
    {
        
        $data = [];
        $serverResult = Http::callApi($service->server, 'servers/external/' . $service->getId() . "?include=allocations,utilization");
        if (!$serverResult->successful()) {
            $data['errors'] = "Server not found";
            return $renderer->render("@pterodactyl/panel", compact('data'));
        }
        $attributes = $serverResult->data()->attributes;

        $utilizationResult = Http::callApi($service->server, 'servers/' . $attributes->identifier . "/resources", [], 'GET', true, 'client');
        if (!$attributes->container->installed) {
            $data['errors'] = "Server not installed";
        }
        $schema = $service->server->isSecure() ? 'https://' : 'http://';
        $ip = $service->server->getIpaddress();
        $data['href'] =  sprintf('%s%s/server/%s', $schema, $ip, $attributes->identifier);
    
        if ($attributes->suspended) {
            $data['errors'] = "Server suspended";
        }
        $data['utilization'] = $utilizationResult->data()->attributes;
        $data['attributes'] = $attributes;
        $data['service'] = $service;
        $data['ips'] = collect($attributes->relationships->allocations->data)->map(function($data){
            return $data->attributes->alias ? $data->attributes->alias : $data->attributes->ip  . ":" . $data->attributes->port;
        })->join(',');
        $data['inAdmin'] = true;
        $data['stats'] = self::STATS;
        return $renderer->render("@pterodactyl/panel", $data);
    }
}