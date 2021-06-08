<?php
namespace App\Pterodactyl\Database;

use App\Shop\Entity\OrderItem;

class ServersTable extends \ClientX\Database\Table
{

    
    protected $table = "pterodactyl_servers";


    public function findServerIdFromServiceId(int $serviceId)
    {
        try {
            return $this->makeQuery()
            ->select("server_id")
            ->where('service_id = :serviceId')
            ->params(compact('serviceId'))
            ->first()->serverId;
        } catch (\ClientX\Database\NoRecordException $e) {
            return null;
        }
    }

    public function saveServer(array $serverData, OrderItem $item)
    {
        $limit = $serverData['limits'];
        $feature =  $serverData['feature_limits'];
        $deploy = $serverData['deploy'];
        return $this->makeQuery()
        ->setCommand("INSERT")
        ->params([
            'service_id' => $serverData['external_id'],
            'server_id' => $item->getServer()->getId(),
            'product_id' => $item->getItem()->getOrderable()->getId()
        ])->execute();
    }
}
