<?php

namespace App\Pterodactyl\Panel;

use App\Pterodactyl\Http;
use App\Shop\Entity\Service;
use App\Shop\Panel\PanelInterface;
use ClientX\Renderer\RendererInterface;
use App\Auth\Database\UserTable;

class PterodactylPanel implements PanelInterface
{
    const STATS = [
        "memory" => ["secondary", "fas fa-atom"],
        "swap" => ["info", "fas fa-memory", "used"],
        "disk" => ["success", "fas fa-microchip"],
        "io" => ["danger", "fas fa-hdd"],
        "cpu" => ["dark", "fas fa-hdd"]
    ];

     
    private UserTable $table;
    
    public function __construct(UserTable $table)
    {
        $this->table = $table;
    }

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
            return $renderer->render("@pterodactyl/panel", compact('data'));
        }
        $schema = $service->server->isSecure() ? 'https://' : 'http://';
        $ip = $service->server->getIpaddress();
        $data['href'] =  sprintf('%s%s/server/%s', $schema, $ip, $attributes->identifier);
        if ($attributes->suspended) {
            $data['errors'] = "Server suspended";
            return $renderer->render("@pterodactyl/panel", compact('data'));
        }
        
        
        if (property_exists($utilizationResult->data(), 'errors')) {
            $data['errors'] = $utilizationResult->data()->errors[0]->detail;
            return $renderer->render("@pterodactyl/panel", compact('data'));
        }
        $data['utilization'] = $utilizationResult->data()->attributes;
        $data['attributes'] = $attributes;
        $data['service'] = $service;
        if ($attributes->suspended) {
            $data['errors'] = "Server suspended";
            return $renderer->render("@pterodactyl/panel", compact('data'));
        }
        
        if (property_exists($attributes->relationships->allocations, 'data')) {
            $data['ips'] = collect($attributes->relationships->allocations->data)->map(function ($data) {
                return $data->attributes->alias ? $data->attributes->alias : $data->attributes->ip  . ":" . $data->attributes->port;
            })->join(',');
        }
        
        $data['user'] = $this->table->find($service->getUserId());
        $data['inAdmin'] = false;
        $data['stats'] = self::STATS;
        if ($attributes->limits->io == 500) {
            unset($data['stats']['io']);
        }

        if ($attributes->limits->swap == -1) {
            unset($data['stats']['swap']);
        }
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
            return $renderer->render("@pterodactyl/panel", compact('data'));
        }
        
        $data['user'] = $this->table->find($service->getUserId());
        $schema = $service->server->isSecure() ? 'https://' : 'http://';
        $ip = $service->server->getIpaddress();
        $data['href'] =  sprintf('%s%s/server/%s', $schema, $ip, $attributes->identifier);

        if ($attributes->suspended) {
            $data['errors'] = "Server suspended";
            return $renderer->render("@pterodactyl/panel", compact('data'));
        }
        
        
        if (property_exists($utilizationResult->data(), 'errors')) {
            $data['errors'] = $utilizationResult->data()->errors[0]->detail;
            return $renderer->render("@pterodactyl/panel", compact('data'));
        }
        $data['utilization'] = $utilizationResult->data()->attributes;
        $data['attributes'] = $attributes;
        $data['service'] = $service;

        if (property_exists($attributes->relationships->allocations, 'data')) {
            $data['ips'] = collect($attributes->relationships->allocations->data)->map(function ($data) {
                return $data->attributes->alias ?: $data->attributes->ip  . ":" . $data->attributes->port;
            })->join(',');
        }

        $data['inAdmin'] = true;
        $data['stats'] = self::STATS;

        if ($attributes->limits->io == 500) {
            unset($data['stats']['io']);
        }

        if ($attributes->limits->swap == -1) {
            unset($data['stats']['swap']);
        }
        return $renderer->render("@pterodactyl/panel", $data);
    }
}
