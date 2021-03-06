<?php


namespace App\Pterodactyl;

use App\Admin\Database\ServerTable;
use App\Pterodactyl\Actions\PterodactylConfigAction;
use App\Pterodactyl\Database\PterodactylTable;
use ClientX\Database\NoRecordException;
use ClientX\Renderer\RendererInterface;
use ClientX\Validator;
use function ClientX\request;

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
        $validator->notEmpty('eggname');

        return $validator;
    }

    public function params(array $params): array
    {
        if (!array_key_exists('productId', $params)) {
            return [];
        }
        $productId = $params['productId'];
        $config = $this->table->findConfig($productId);
        $eggsAndNest = json_decode($config->eggs, true);

        [$inFiveM, $eggs] = $this->getEggsAndFiveM($eggsAndNest, $config->serverId);
        if (count($eggs) == 1) {
            $params['eggname'] = current($eggs);
        }
        [$nestId, $eggId] = $this->getEggsIdFromName($params['eggname'], $eggsAndNest, $config->serverId);
        $params['eggId'] = $eggId;
        $params['nestId'] = $nestId;

        return array_filter($params, function ($key) {
            return in_array($key, ["eggId", 'nestId', 'FIVEM_LICENSE', "eggname"]);
        }, ARRAY_FILTER_USE_KEY);
    }

    public function render(RendererInterface $renderer, array $data = []): string
    {
        $productId = $data['product']->getId();
        try {
            $config = $this->table->findConfig($productId);
        } catch (NoRecordException $e) {
            return 'Configuration not found';
        }
        $eggsAndNest = json_decode($config->eggs, true);
        [$inFiveM, $eggs] = $this->getEggsAndFiveM($eggsAndNest, $config->serverId);
        $errors = $data['errors'];
        $item = $data['item'];
        $params = request()->getParsedBody();
        if (array_key_exists('eggname', $params)) {
            $item['eggname'] = $params['eggname'];
        }
        $inAdmin = $data['inAdmin'] ?? false;
        return $renderer->render("@pterodactyl/data", compact('eggs', 'productId', 'item', 'errors', 'inAdmin'));
    }

    private function getEggsAndFiveM(array $eggsAndNest, ?int $serverId = null): array
    {
        $eggs = [];
        $inFiveM = false;
        foreach ($eggsAndNest as $value) {
            [$egg, $nest] = explode(PterodactylConfigAction::DELIMITER, $value);

            if ($serverId){
                $server = $this->serverTable->find($serverId);
            } else {
                $server = $this->serverTable->findFirst(['pterodactyl']);
            }
            $response = Http::callApi($server, "nests/$nest/eggs/$egg?include=variables");
            if ($response->status() == 200) {
                $response = $response->data();
                $eggs[$response->attributes->name] = $response->attributes->name;
            }
        }
        return [$inFiveM, $eggs];
    }

    private function getEggsIdFromName(string $eggname, $eggs, ?int $serverId = null)
    {
        foreach ($eggs as $value) {
            [$egg, $nest] = explode(PterodactylConfigAction::DELIMITER, $value);
            if ($serverId){
                $server = $this->serverTable->find($serverId);
            } else {
                $server = $this->serverTable->findFirst(['pterodactyl']);
            }

            $response = Http::callApi($server, "nests/$nest/eggs/$egg");
            if ($response->status() == 200) {
                $response = $response->data();
                if ($response->attributes->name === $eggname) {
                    return [$nest, $egg];
                }
            }
        }
    }

    private function checkIfFiveMEgg(array $data, ?int $serverId = null)
    {
        $nest = $data['nestId'] ?? 0;
        $egg = $data['eggId'] ?? 0;
        if ($serverId){
            $server = $this->serverTable->find($serverId);
        } else {
            $server = $this->serverTable->findFirst(['pterodactyl']);
        }
        $inFiveM = false;
        $response = Http::callApi($server, "nests/$nest/eggs/$egg?include=variables");
        if ($response->status() == 200) {
            $response = $response->data();
            foreach ($response->attributes->relationships->variables->data as $key) {
                if ($key->attributes->env_variable === 'FIVEM_LICENSE' && (is_null($key->attributes->default_value)|| empty($key->attributes->default_value))) {
                    $inFiveM = true;
                }
            }
        }
        return $inFiveM;
    }
}
