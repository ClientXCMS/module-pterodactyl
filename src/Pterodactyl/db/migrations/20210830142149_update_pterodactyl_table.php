<?php

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

class UpdatePterodactylTable extends AbstractMigration
{
    public function change()
    {
        $this->table("pterodactyl_config")
            ->addColumn("eggs", "text", ['limit' => MysqlAdapter::TEXT_LONG])
            ->save();
    }
}
