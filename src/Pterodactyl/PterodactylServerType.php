<?php

namespace App\Pterodactyl;

use App\Account\User;
use App\Pterodactyl\Actions\PterodactylConfigAction;
use App\Admin\Database\ServerTable;
use App\Admin\Entity\Server;
use App\Pterodactyl\Database\PterodactylTable;
use App\Pterodactyl\Database\ServersTable;
use App\Shop\Entity\Order;
use App\Shop\Entity\OrderItem;
use App\Shop\Entity\Product;
use App\Shop\Entity\Upgrade;
use ClientX\Helpers\Passwords;
use ClientX\Helpers\Str;
use ClientX\Response\ConnectionResponse;
use ClientX\ServerTypeInterface;
use ClientX\ServerUpgradeInterface;
use ClientX\Translator\Translater;
use ClientX\Validator;
use Exception;
use App\Shop\Entity\Service;
use GuzzleHttp\Psr7\Response;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

class PterodactylServerType implements ServerTypeInterface, ServerUpgradeInterface
{
    protected ServerTable $server;
    protected PterodactylTable $pterodactyl;
    private ServersTable $servers;
    private Translater $translater;
    private LoggerInterface $logger;
    /**
     * @var \App\Pterodactyl\PterodactylMailer
     */
    private PterodactylMailer $mailer;
    /**
     * @var \Psr\Container\ContainerInterface
     */
    protected ContainerInterface $container;

    public function __construct(
        LoggerInterface $logger,
        PterodactylTable $pterodactyl,
        ServersTable $servers,
        ServerTable $server,
        ContainerInterface $container,
        Translater $translater
    ) {
        $this->logger = $logger;
        $this->server = $server;
        $this->servers = $servers;
        $this->pterodactyl = $pterodactyl;
        $this->translater = $translater;
        $this->container = $container;
    }

    public function findServer(OrderItem $item): ?Server
    {
        if ($item->getService() != null) {
            return $this->server->find($item->getService()->server->getId());
        }

        $config = $this->pterodactyl->findConfig($item->getItem()->getOrderable()->getId());
        $serverId = $config->serverId;
        if ($serverId) {
            $server = $this->server->find($serverId);
        } else {
            $server = $this->server->findFirst($this->for());
        }
        return $server;
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
            $response = Http::callApi($server, 'servers', [], 'POST')->getResponse();
            $body = json_decode($response->getBody()->__toString(), true);

            if ($body != null && $body['errors'][0]['code'] == "ValidationException") {
                $response = $response->withStatus(200);
            }
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
            ->notEmpty('ipaddress', 'password', 'username');
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
            
            $data = [];
            $result = null;
            $userResult = Http::callApi($item->getServer(), "users?per_page=300&page=1");
            $userData = $userResult->data()->data;

            foreach ($userData as $key => $value) {
                if (strtolower($value->attributes->email) == strtolower($user->getEmail())) {
                    $result = $value->attributes;
                    break;
                }
            }
        
            for($i = 2; $i <= $userResult->data()->meta->pagination->total_pages;$i++) {
                $userResult = Http::callApi($item->getServer(), "users?per_page=300&page=$i");
                $userData = $userResult->data()->data;
                foreach ($userData as $key => $value) {
                    if (strtolower($value->attributes->email) == strtolower($user->getEmail())) {
                        $result = $value->attributes;
                        break;
                    }
                }
            }
                if ($result === null) {
                    $password = Str::randomStr(10);
                    $make = $this->makeAccount($user, $item->getServer(), $password)->data();
                    if (property_exists($make, 'errors')){
                        return json_encode($make->errors);
                    }
                    $result = $make->attributes;
                } else {
                    $password = "Already set";
                    
                    $serverResult = Http::callApi($item->getServer(), 'servers');
                    $i = 0;
                    foreach ($serverResult->data()->data as $key => $value) {
                        if ($value->attributes->user == $result->id) {
                            $i++;
                        }
                    }
                    if ($i === 0) {
                        $password = Str::randomStr(10);
                        $updateResult = Http::callApi($item->getServer(), 'users/' . $result->id, [
                            'username' => $result->username,
                            'email' => $result->email,
                            'first_name' => $result->first_name,
                            'last_name' => $result->last_name,
                            'password' => $password,
                        ], 'PATCH');
                        if ($updateResult->status() !== 200) {
                            $this->error("changepwd", $updateResult->status());
                        }
                    }
            }
            $userId = $result->id;
            $eggs = json_decode($config->eggs, true);
            
            if (count($eggs) == 1 || !array_key_exists('nestId', $params)) {
                $first = current($eggs);
                [$eggId, $nestId] = explode(PterodactylConfigAction::DELIMITER, $first);
            } else {
                $nestId = $params['nestId'] ?? '';
                $eggId = $params['eggId'] ?? '';
            }
            [$environment, $eggResult] = $this->getEnvFromNest($eggId, $nestId, $item->getServer(), $params);
            $name = $params['options']['servername']['value'] ?? $this->placeholder($item, $item->getOrder(), $config->servername ?? Str::randomStr(10) . ' # ' . $item->getService()->getId());
            $memory = $config->memory + ($params['options']['memory']['value'] ?? 0) * 1024;
            $swap = $config->swap;
            $io = $config->io;
            $cpu = $config->cpu;
            $disk = $config->disk + ($params['options']['disk']['value']  ?? 0) * 1024;
            $location_id = $config->locationId;
            $dedicated_ip = (bool)$config->dedicatedip;
            $port_range = isset($config->portRange) ? explode(',', $config->portRange) : [];
            $port_range = collect($port_range)->map(function ($range) {
                return (string) $range;
            })->toArray();
            $image = $config->image ?? $eggResult->data()->attributes->docker_image;
            $startup = $config->startup ?? $eggResult->data()->attributes->startup;
            $databases = $config->db + ($params['options']['database']['value'] ?? 0);
            $allocations = ($config->allocations ?? 0) + ($params['options']['allocation']['value'] ?? 0);
            $backups = $config->backups + ($params['options']['backup']['value'] ?? 0);

            $oom_disabled = (bool)$config->oomKill;
            try {
                $serviceId = (string)$item->getService()->getId();
                $id = $this->getServerId($serviceId, $item->getServer());

                if ($id != null) {
                    $this->container->get(PterodactylMailer::class)->sendTo($item->getOrder()->getUser(), $item->getServer(), $item->getService(), $password);
                    $this->servers->saveServer($serviceId, $item->getServer()->getId(), $item->getItem()->getOrderable()->getId());
                    return 'success';
                }
            } catch (\Exception $e) {
            }
            $serverData = [
                'name' => $name,
                'user' => (int)$userId,
                'nest' => (int)$nestId,
                'egg' => (int)$eggId,
                'description' => $item->getService()->getExpireAt() != null ? "Exp: " . $item->getService()->getExpireAt()->format('d/m/y H:i:s') : ' Exp: None',
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
                //$this->logger->critical($server->toJson());

                $this->error("satisfying");
            }
            if ($server->status() !== 201) {
                $this->logger->critical($server->toJson());
                $this->error("createserver", $server->status());
            }
            $this->container->get(PterodactylMailer::class)->sendTo($item->getOrder()->getUser(), $item->getServer(), $item->getService(), $password);
            $this->servers->saveServer($serviceId, $item->getServer()->getId(), $item->getItem()->getOrderable()->getId());
        } catch (Exception $e) {
            return $e->getMessage();
        }
        return "success";
    }

    public function expireAccount(Service $service): string
    {
        return $this->terminateAccount($service);
    }
    
    
    public function removeOption(string $option, Service $service): string
    {
        return "failed";
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

    public function addOptions(array $options, Service $service): string
    {
        return "failed";
    }

    public function upgradeProduct(Upgrade $upgrade): string
    {
        $server = $upgrade->getService()->server;
        $serverId = $this->getServerId($upgrade->getServiceId(), $server);
        $request = Http::callApi($server, "servers/$serverId");
        if ($request->successful()) {
            $data = $request->data()->attributes;
            $config = $this->pterodactyl->findConfig($upgrade->getNewproductId());
            $memory = $config->memory;
            $swap = $config->swap;
            $io = $config->io;
            $cpu = $config->cpu;
            $disk = $config->disk;

            $databases = $config->db;
            $allocations = $config->allocations ?? 0;
            $backups = $config->backups;
            $patch = Http::callApi($server, "servers/$serverId/build", [
                'allocation' => $data->allocation,
                'memory' => (int)$memory,
                'swap' => (int)$swap,
                'io' => (int)$io,
                'cpu' => (int)$cpu,
                'disk' => (int)$disk,
                'feature_limits' => [
                    'databases' => $databases ? (int)$databases : null,
                    'allocations' => (int)$allocations,
                    'backups' => (int)$backups,
                ]
            ], 'PATCH');
            if ($patch->successful()) {
                return "success";
            }
            return json_decode($patch->toJson());
        }
        return json_decode($request->toJson());

        return "failed";
    }

    public function changePassword(Service $service, ?string $password = null): string
    {
        if ($password === null) {
            return 'can';
        }
        try {
            if ($password === '') {
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

    protected function makeAccount(User $user, Server $server, string $password)
    {
        return Http::callApi($server, 'users', [
            "username" => Str::slugify($user->getName()) . $user->getId(),
            "email" => $user->getEmail(),
            "first_name" => $user->getFirstname(),
            "last_name" => $user->getLastname(),
            "external_id" => (string)"CLIENTXCMS2-" . str_pad($user->getId(), 5),
            "password" => $password,
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
            $envName = $data[$attr->env_variable] ?? null;
            
            if ($attr->env_variable == "FIVEM_LICENSE" && ($envName == null || empty($envName))){
                $default = "Change me";
            }
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
                //$this->error($terms, $action->status());
            }
        } catch (Exception $err) {
            //return $err->getMessage();
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

    private function placeholder(OrderItem $orderItem, Order $order, string $message)
    {

        /** @var \App\Account\User $user */
        $user = $order->getUser();
        $context = [
            'owner_email' => $user->getEmail(),
            'owner_username' => $user->getName(),
            'owner_firstname' => $user->getFirstname(),
            'owner_lastname' => $user->getLastname(),
            'order_id' => $order->getId(),
            'product_name' => $orderItem->getItem()->getName(),
            'service_id' => $orderItem->getService()->getId(),
        ];
        return str_replace('%', '', str_replace(array_keys($context), array_values($context), $message));
    }
}
