<?php 

namespace App\Pterodactyl;

use App\Account\User;
use App\Pterodactyl\Actions\PterodactylConfigAction;
use App\Admin\Database\ServerTable;
use App\Admin\Entity\Server;
use App\Pterodactyl\Database\PterodactylTable;
use App\Pterodactyl\Database\ServersTable;
use App\Shop\Entity\OrderItem;
use App\Shop\Entity\Product;
use ClientX\Helpers\Str;
use ClientX\Response\ConnectionResponse;
use ClientX\ServerTypeInterface;
use ClientX\Translator\Translater;
use ClientX\Validator;
use Exception;
use App\Shop\Entity\Service;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use RuntimeException;

class PterodactylServerType implements ServerTypeInterface
{
    private ServerTable $server;
    private PterodactylTable $pterodactyl;
    private ServersTable $servers;
    private Translater $translater;
    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $logger,
        PterodactylTable $pterodactyl,
        ServersTable $servers,
        ServerTable $server,
        Translater $translater
    )
    {
        $this->logger = $logger;
        $this->server = $server;
        $this->servers = $servers;
        $this->pterodactyl = $pterodactyl;
        $this->translater = $translater;
    }

    public function findServer(OrderItem $item): ?Server
    {
        if ($item->getService() != null) {
            return $this->server->find($item->getService()->server->getId());
        }
        return $this->server->findFirst($this->for());
    }

    public function changeName(Service $service, ?string $name = null): ?string
    {
        $serverData = $this->getServerId($service->getId(), $service->server, true);
        if (!isset($serverData)) {
            $this->error("linkserver");
        }
        $id = $serverData->data()->attributes->id;
        $call = Http::callApi($service->server, "servers/$id/details", [

            'name' => $name,
            'description' => $serverData->data()->attributes->description,
            'external_id' => $serverData->data()->attributes->external_id,
            'user' => $serverData->data()->attributes->user
        ], 'PATCH');
        return "success";
    }

    public function for(): array
    {
        return [
            "pterodactyl",
        ];
    }

    public function type(): string
    {
        return "pterodactyl";
    }

    public function name(): string
    {
        return "Pterodactyl";
    }

    public function testConnection(array $params): ConnectionResponse
    {
        try {
            $server = (new Server())
                ->setUsername($params['username'])
                ->setPassword($params['password'])
                ->setIpaddress($params['ipaddress'])
                ->setCertificate($params['certificate'])
                ->setSecure($params['secure'] ?? false);
            $response = Http::callApi($server, 'servers', [], 'GET', true)->getResponse();
            $response2 = Http::callApi($server, '', [], 'GET', true, 'client')->getResponse();
            $success = $response->getStatusCode() == 200 && $response2->getStatusCode() == 200;
            $response = new Response($success ? 200 : 500, [], $success ? 'Application & Client : Success' : "Applications : " . $response->getBody()->__toString() . PHP_EOL . " Client : " . $response2->getBody()->__toString());
        } catch (\Exception  $e) {
            $defaultResponse = new Response(500, [], $e->getMessage());
            $response = $e->getResponse() ?? $defaultResponse;
        }
        return new ConnectionResponse($response);
    }

    public function validate(array $params): Validator
    {
        return (new Validator($params))
            ->notEmpty('ipaddress', 'password', 'username')
            ->inArray('secure', [0, 1]);
    }

    public function createAccount(OrderItem $item): string
    {
        $orderable = $item->getItem()->getOrderable();
        if (!$orderable instanceof Product) {
            return "failed";
        }
        try {

            $params = $item->getData();
            $config = $this->pterodactyl->findConfig($orderable->getId());
            $user = $item->getOrder()->getUser();
            $userResult = Http::callApi($item->getServer(), 'users/external/' . $user->getId());
            $data = [];
            $userResult = Http::callApi($item->getServer(), 'users');

            $data = array_merge($userResult->data()->data, $data);
            $result = null;
            foreach ($userResult->data()->data as $key => $value) {
                if ($value->attributes->email == $user->getEmail()) {
                    $result = $value->attributes;
                    break;
                }

            }
            if ($result === null) {
                $result = $this->makeAccount($user, $item->getServer())->data()->attributes;
            }
            $userId = $result->id;
            
            $eggs = json_decode($config->eggs, true);
            if (count($eggs) == 1){
                $first = current($eggs);
                [$eggId, $nestId] = explode(PterodactylConfigAction::DELIMITER, $first);
            } else {
                $nestId = $params['nestId'];
                $eggId = $params['eggId'];
            }
            [$environment, $eggResult] = $this->getEnvFromNest($eggId, $nestId, $item->getServer(), $params);
            $name = $config->servername ?? Str::randomStr(10) . ' # ' . $item->getService()->getId();
            $memory = $config->memory;
            $swap = $config->swap;
            $io = $config->io;
            $cpu = $config->cpu;
            $disk = $config->disk;
            $location_id = $config->locationId;
            $dedicated_ip = (bool)$config->dedicatedip;
            $port_range = $config->portRange;
            $port_range = isset($portRange) ? explode(',', $portRange) : [];
            $image = $config->image ?? $eggResult->data()->attributes->docker_image;
            $startup = $config->startup ?? $eggResult->data()->attributes->startup;
            $databases = $config->db;
            $allocations = $config->allocations ?? 0;
            $backups = $config->backups;


            $oom_disabled = (bool)$config->oomKill;

            $serverData = [
                'name' => $name,
                'user' => (int)$userId,
                'nest' => (int)$nestId,
                'egg' => (int)$eggId,
                'docker_image' => $image,
                'startup' => $startup,
                'oom_disabled' => $oom_disabled,
                'limits' => [
                    'memory' => (int)$memory,
                    'swap' => (int)$swap,
                    'io' => (int)$io,
                    'cpu' => (int)$cpu,
                    'disk' => (int)$disk,
                ],
                'feature_limits' => [
                    'databases' => $databases ? (int)$databases : null,
                    'allocations' => (int)$allocations,
                    'backups' => (int)$backups,
                ],
                'deploy' => [
                    'locations' => [(int)$location_id],
                    'dedicated_ip' => $dedicated_ip,
                    'port_range' => $port_range,
                ],
                'environment' => $environment,
                'start_on_completion' => true,
                'external_id' => (string)$item->getService()->getId(),
            ];
            $server = Http::callApi($item->getServer(), 'servers', $serverData, 'POST');
            if ($server->status() === 400) {
                $this->error("satisfying");
            }
            if ($server->status() !== 201) {
                $this->logger->critical($server->toJson());
                $this->error("createserver", $server->status());
            }
            $this->servers->saveServer($serverData, $item);
        } catch (Exception $e) {
            return $e->getMessage();
        }
        return "success";
    }

    public function expireAccount(Service $service): string
    {
        return $this->suspendAccount($service);
    }

    public function suspendAccount(Service $service): string
    {
        return $this->changeAccountStatus("POST", "suspend", $service->getId(), $service->server);
    }

    public function unsuspendAccount(Service $service): string
    {
        return $this->changeAccountStatus("POST", "unsuspend", $service->getId(), $service->server);
    }

    public function terminateAccount(Service $service): string
    {
        return $this->changeAccountStatus("DELETE", "terminate", $service->getId(), $service->server);
    }


    public function changePassword(Service $service, ?string $password = null): string
    {
        if ($password === null) {
            return 'can';
        }
        try {
            if ($password === '' || $password === null) {
                $this->error("pdwempty");
            }
            $serverData = $this->getServerId($service->getId(), $service->server, true);
            if (!isset($serverData)) {
                $this->error("linkserver");
            }
            $userId = $serverData->data()->attributes->user;
            $userResult = Http::callApi($service->server, 'users/' . $userId);
            if ($userResult->status() !== 200) {
                $this->error("retrieveuser", $userResult->status());
            }
            $updateResult = Http::callApi($service->server, 'users/' . $userId, [
                'username' => $userResult->data()->attributes->username,
                'email' => $userResult->data()->attributes->email,
                'first_name' => $userResult->data()->attributes->first_name,
                'last_name' => $userResult->data()->attributes->last_name,
                'password' => $password,
            ], 'PATCH');
            if ($updateResult->status() !== 200) {
                $this->error("changepwd", $updateResult->status());
            }
        } catch (Exception $e) {
            return $e->getMessage();
        }
        return "success";
    }

    public function changePackage(Service $service, OrderItem $item): string
    {
        return "failed";
    }

    private function makeAccount(User $user, Server $server)
    {
        return Http::callApi($server, 'users', [
            "username" => Str::slugify($user->getName()) . $user->getId(),
            "email" => $user->getEmail(),
            "first_name" => $user->getFirstname(),
            "last_name" => $user->getLastname(),
            "external_id" => (string)"CLIENTXCMS-" . str_pad($user->getId(), 5),
        ], 'POST');
    }

    private function getServerId(int $serviceId, Server $server, bool $raw = false)
    {
        $serverResult = Http::callApi($server, 'servers/external/' . $serviceId);
        if ($serverResult->successful()) {
            if ($raw) {
                return $serverResult;
            }
            return $serverResult->data()->attributes->id;
        } else {
            $serverId = $this->servers->findServerIdFromServiceId($serviceId);
            if (!is_null($serverId)) {
                if ($raw) {
                    $serverResult = Http::callApi($server, 'servers/' . $serverId);
                    if ($serverResult->successful()) {
                        return $serverResult;
                    } else {
                        $this->error("getserver", $serverResult->status());
                    }
                    return $serverId;
                }
            }
        }
    }

    private function getEnvFromNest(int $eggId, int $nestId, $server, $data)
    {
        $eggResult = Http::callApi($server, 'nests/' . $nestId . '/eggs/' . $eggId . "?include=variables");
        if (!$eggResult->successful()) {
            $this->error("geteggs", $eggResult->status());
        }
        $environment = [];
        foreach ($eggResult->data()->attributes->relationships->variables->data as $key => $val) {
            $attr = $val->attributes;
            $var = $attr->env_variable;
            $default = $attr->default_value;
            $envName = $data[$attr->env_variable] ?? $default;
            $environment[$var] = $envName;
        }
        return [$environment, $eggResult];
    }

    private function changeAccountStatus(string $method, string $terms, string $serviceId, $server)
    {

        try {
            $serverId = $this->getServerId($serviceId, $server);
            if (!isset($serverId)) {
                $this->error($terms . "exist");
            }
            if ($method === 'DELETE') {
                $action = Http::callApi($server, 'servers/' . $serverId, [], $method);
            } else {
                $action = Http::callApi($server, 'servers/' . $serverId . '/' . $terms, [], $method);
            }
            if ($action->status() !== 204) {
                $this->error($terms, $action->status());
            }
        } catch (Exception $err) {
            return $err->getMessage();
        }
        return "success";
    }

    private function error(string $key, ?int $status_code = null)
    {
        if ($status_code === null) {
            $message = $this->translater->trans("pterodactyl.failed." . $key);
        } else {
            $message = $this->translater->trans("pterodactyl.failed." . $key, ['%status_code%' => $status_code]);
        }
        $this->logger->critical($message);
        throw new RuntimeException($message);
    }
}
