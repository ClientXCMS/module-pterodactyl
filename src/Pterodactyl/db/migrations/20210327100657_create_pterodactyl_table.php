<?php

use Phinx\Migration\AbstractMigration;

class CreatePterodactylTable extends AbstractMigration
{
    public function change()
    {
        
        $this->dropIfExit("pterodactyl_minecraft_config");
        $this->dropIfExit("pterodactyl_minecraft_servers");
        $this->dropIfExit("pterodactyl_rust_config");
        $this->dropIfExit("pterodactyl_rust_servers");
        $this->dropIfExit("pterodactyl_voiceservers_config");
        $this->dropIfExit("pterodactyl_voiceservers_server");
        $this->dropIfExit("pterodactyl_accounts");
        $this->dropIfExit("pterodactyl_config");
        $this->dropIfExit("pterodactyl_servers");

        $this->table('pterodactyl_config')
            ->addColumn('product_id', 'integer')
            ->addColumn('memory', 'integer')
            ->addColumn('disk', 'integer')
            ->addColumn('io', 'integer')
            ->addColumn('cpu', 'integer')
            ->addColumn('port_range', 'string', ['null' => true])
            ->addColumn('location_id', 'integer')
            ->addColumn('nest_id', 'integer')
            ->addColumn('egg_id', 'integer')
            ->addColumn('backups', 'integer', ['default' => 0])
            ->addColumn('image', 'string', ["null" => true])
            ->addColumn('startup', 'string', ["null" => true])
            ->addColumn('dedicatedip', 'boolean', ["null" => true])
            ->addColumn('oom_kill', 'boolean', ["null" => true])
            ->addColumn('servername', 'string', ["null" => true])
            ->addColumn('db', 'integer', ['default' => 0])
            ->addColumn('swap', 'integer', ['default' => 0])
            ->addForeignKey('product_id', 'products', ['id'], ['delete' => 'cascade'])
            ->addIndex('product_id', ['unique' => true])
            ->create();


        $this->table('pterodactyl_servers')
            ->addColumn('service_id', 'integer')
            ->addColumn('server_id', 'integer')
            ->addColumn('product_id', 'integer')
            ->addForeignKey('service_id', 'services', ['id'], ['delete' => 'cascade'])
            ->addForeignKey('product_id', 'products', ['id'], ['delete' => 'cascade'])
            ->addIndex('service_id', ['unique' => true])
            ->create();
    }

    private function dropIfExit(string $tableName)
    {
        $table = $this->table($tableName);
        if ($table->exists()) {
            $table->drop()->save();
        }
    }
}
