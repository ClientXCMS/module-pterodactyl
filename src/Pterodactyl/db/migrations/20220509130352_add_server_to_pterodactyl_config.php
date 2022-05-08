<?php

use Phinx\Migration\AbstractMigration;

class AddServerToPterodactylConfig extends AbstractMigration
{
    public function change()
    {
        $this->table("pterodactyl_config")
            ->addColumn("server_id", "integer", ['null' => true])
            ->save();
    }
}