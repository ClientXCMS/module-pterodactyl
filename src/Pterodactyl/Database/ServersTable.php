<?php
namespace App\Pterodactyl\Database;

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

    public function saveServer(int $serviceId, int $serverId, int $productId)
    {
        return $this->makeQuery()
        ->setCommand("INSERT")
        ->params([
            'service_id' => $serviceId,
            'server_id' => $serverId,
            'product_id' => $productId
        ])->execute();
    }
}