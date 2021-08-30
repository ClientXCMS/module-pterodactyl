<?php


namespace App\Pterodactyl;

use App\Pterodactyl\Database\PterodactylTable;
use ClientX\App;
use Phinx\Migration\Manager;
use App\Pterodactyl\Actions\PterodactylConfigAction;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

trait PterodactylTrait
{

    private function changeEggs(PterodactylTable $table)
    {
        $existsTable = $table->makeQuery()->raw("SHOW COLUMNS FROM `pterodactyl_config` LIKE 'eggs'");
        if ($existsTable->fetch() == false) {
            $configArray = require("phinx.php");
            $config = new \Phinx\Config\Config($configArray);
            $manager = new Manager($config, new StringInput(' '), new NullOutput());
            $manager->migrate(App::DB_ENV);
            $manager->seed(App::DB_ENV);
        }
        $configs = $table->findAll2();
        foreach ($configs as $config) {
            if (empty($config->eggs)) {

                $eggs = [join(PterodactylConfigAction::DELIMITER, [$config->eggId, $config->nestId])];
                $configId = $config->productId;
                $table->updateConfig($config->id, $configId, ['eggs' => $eggs]);
            }
        }
    }
}