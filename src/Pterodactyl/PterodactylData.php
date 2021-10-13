<?php /** @noinspection ALL */


namespace App\Pterodactyl;

use App\Admin\Database\ServerTable;
use App\Pterodactyl\Actions\PterodactylConfigAction;
use App\Pterodactyl\Database\PterodactylTable;
use ClientX\Renderer\RendererInterface;
use ClientX\Validator;

class PterodactylData implements \ClientX\Product\ProductDataInterface
{
    use PterodactylTrait;

    private PterodactylTable $table;
    /**
     * @var \App\Admin\Database\ServerTable
     */
    private ServerTable $serverTable;

    public function __construct(PterodactylTable $table, ServerTable $serverTable)
    {
        $this->table = $table;
        $this->serverTable = $serverTable;
    }

    public function primary()
    {
        return 'eggname';
    }

    public function validate(array $data): Validator
    {
        $validator = new Validator($data);
        if ($this->checkIfFiveMEgg($data)) {
            $validator->notEmpty('FIVEM_LICENSE');
        }
        $validator->notEmpty('eggname');
        return $validator;
    }

    public function params(array $params): array
    {
        $productId = $params['productId'];
        $config = $this->table->findConfig($productId);
        $eggsAndNest = json_decode($config->eggs, true);
        if (count($eggsAndNest) == 1) {
            $params['eggname'] = current($eggsAndNest);
        }
        [$nestId, $eggId] = $this->getEggsIdFromName($params['eggname'], $eggsAndNest);
        $params['eggId'] = $eggId;
        $params['nestId'] = $nestId;

        return array_filter($params, function ($key) {
            return in_array($key, ["eggId", 'nestId', 'FIVEM_LICENSE', "eggname"]);
        }, ARRAY_FILTER_USE_KEY);
    }

    public function render(RendererInterface $renderer, array $data = []): string
    {
        $productId = $data['product']->getId();
        $config = $this->table->findConfig($productId);
        //$this->changeEggs($this->table);
        $eggsAndNest = json_decode($config->eggs, true);
        [$inFiveM, $eggs] = $this->getEggsAndFiveM($eggsAndNest);
        $errors = $data['errors'];
        $item = $data['item'];
        $inAdmin = $data['inAdmin'] ?? false;
        return $renderer->render("@pterodactyl/data", compact('inFiveM', 'eggs', 'productId', 'item', 'errors', 'inAdmin'));
    }

    private function getEggsAndFiveM(array $eggsAndNest): array
    {
        $eggs = [];
        $inFiveM = false;
        foreach ($eggsAndNest as $value) {

            [$egg, $nest] = explode(PterodactylConfigAction::DELIMITER, $value);

            $server = $this->serverTable->findFirst(['pterodactyl']);
            $response = Http::callApi($server, "nests/$nest/eggs/$egg?include=variables");
            if ($response->status() == 200) {
                $response = $response->data();
                $eggs[$response->attributes->name] = $response->attributes->name;

                foreach ($response->attributes->relationships->variables->data as $key) {
                    if ($key->attributes->env_variable === 'FIVEM_LICENSE' && is_null($key->attributes->default_value)) {
                        $inFiveM = true;
                    }
                }
            }
        }
        return [$inFiveM, $eggs];
    }

    private function getEggsIdFromName(string $eggname, $eggs)
    {
        foreach ($eggs as $value) {
            [$egg, $nest] = explode(PterodactylConfigAction::DELIMITER, $value);
            $server = $this->serverTable->findFirst(['pterodactyl']);

            $response = Http::callApi($server, "nests/$nest/eggs/$egg");
            if ($response->status() == 200) {
                $response = $response->data();
                if ($response->attributes->name === $eggname) {
                    return [$nest, $egg];
                }
            }
        }
    }

    private function checkIfFiveMEgg(array $data)
    {
        $nest = $data['nestId'] ?? 0;
        $egg = $data['eggId'] ?? 0;
        $server = $this->serverTable->findFirst(['pterodactyl']);
        $inFiveM = false;
        $response = Http::callApi($server, "nests/$nest/eggs/$egg?include=variables");
        if ($response->status() == 200) {
            $response = $response->data();
            foreach ($response->attributes->relationships->variables->data as $key) {
                if ($key->attributes->env_variable === 'FIVEM_LICENSE' || empty($data['fiveM_key']) && is_null($key->attributes->default_value)) {
                    $inFiveM = true;
                }
            }
        }
        return $inFiveM;
    }
}
