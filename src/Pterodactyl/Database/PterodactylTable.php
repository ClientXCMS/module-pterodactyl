<?php
namespace App\Pterodactyl\Database;

use ClientX\Database\AbstractConfigurationTable;
use ClientX\Database\Query;

class PterodactylTable extends AbstractConfigurationTable
{

    protected $table = "pterodactyl_config";

    public function createConfig(int $productId, array $data = []): bool
    {
        $data = array_merge($data, ['product_id' => $productId]);
        return $this->insert($data);
    }

    public function updateConfig(int $id, int $productId, array $data): bool
    {
        return $this->update($id, $data);
    }

    public function findAll(): Query
    {
        return $this->makeQuery()
            ->select("memory", "disk", "products.name", "p.id", "products.id as productId")
            ->join("products ", "products.id = p.product_id");
    }
}
