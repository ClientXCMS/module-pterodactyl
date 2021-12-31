<?php

namespace App\Pterodactyl\Database;

use App\Pterodactyl\Actions\PterodactylConfigAction;
use ClientX\Database\AbstractConfigurationTable;
use ClientX\Database\Query;

class PterodactylTable extends AbstractConfigurationTable
{

    protected $table = "pterodactyl_config";

    public function createConfig(int $productId, array $data = []): bool
    {

        $data['eggs'] = json_encode($data['eggs']);
        $data['egg_id'] = 0;
        $data['nest_id'] = 0;
        $data = array_merge($data, ['product_id' => $productId]);
        
        foreach (["port_range", "servername", "image", "startup", "db", "backups"] as $key) {
            if (empty($data[$key])) {
                if (in_array($key, ["db", "backups"])) {
                    $data[$key] = 0;
                } else {
                    $data[$key] = null;
                }
            }
        }
        return $this->insert($data);
    }

    public function updateConfig(int $id, int $productId, array $data): bool
    {

        $data['eggs'] = json_encode($data['eggs']);
        
        foreach (["port_range", "servername", "image", "startup", "db", "backups"] as $key) {
            if (empty($data[$key])) {
                if (in_array($key, ["db", "backups"])) {
                    $data[$key] = 0;
                } else {
                    $data[$key] = null;
                }
            }
        }
        $data['egg_id'] = 0;
        $data['nest_id'] = 0;
        return $this->update($id, $data);
    }

    public function findAll(): Query
    {
        return $this->makeQuery()
            ->select("memory", "disk", "products.name", "p.id", "products.id as productId")
            ->join("products ", "products.id = p.product_id");
    }


    public function findAll2(): Query
    {
        return parent::findAll();
    }
}
