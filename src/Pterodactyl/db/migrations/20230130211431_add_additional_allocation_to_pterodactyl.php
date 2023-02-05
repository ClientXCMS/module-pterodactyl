<?php

use Phinx\Migration\AbstractMigration;

class AddAdditionalAllocationToPterodactyl extends AbstractMigration
{
    public function change()
    {
        $this->table('pterodactyl_config')
            ->addColumn("allocations", "integer", ['default' => 0])->save();
    }
}
