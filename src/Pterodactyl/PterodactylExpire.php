<?php

namespace App\Pterodactyl;

use App\Shop\Event\Services\AddExpireEvent;

class PterodactylExpire
{

    public function __invoke(AddExpireEvent $event)
    {
        $service = $event->getTarget();
        if ($service->getType() !== 'pterodactyl' && $service->getExpireAt() == null) {
            return;
        }
        $serverResult = Http::callApi($service->server, 'servers/external/' . $service->getId() . "?include=allocations,utilization");
        if (!$serverResult->successful()) {
            return;
        }
        $id = $serverResult->data()->attributes->id;
        $serverData = Http::callApi($service->server, 'servers/' . $id)->data();

        $response = Http::callApi(
            $service->server,
            "servers/$id/details",
            [
                'name' => $serverData->attributes->name,
                'external_id' => (string)$service->getId(),
                'user' => $serverData->attributes->user,
                'description' => "Exp: ". $service->getExpireAt()->format('d/m/y')
            ], "PATCH"
        );
    }
}
