<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

namespace Tests\Modules\Pterodactyl\Fixtures;

use App\Models\Account\Customer;
use App\Models\Provisioning\Server;
use App\Models\Provisioning\Service;
use App\Modules\Pterodactyl\Models\PterodactylConfig;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

trait InteractsWithFakePanel
{
    protected function bootPterodactylModule(): void
    {
        \App\Extensions\ExtensionManager::writeExtensionJson([
            'modules' => [[
                'uuid' => 'pterodactyl',
                'version' => '1.5.0',
                'type' => 'module',
                'installed' => true,
                'enabled' => true,
                'api' => ['providers' => [['provider' => 'App\Modules\Pterodactyl\PterodactylServiceProvider']]],
            ]],
        ]);
        app('extension')->autoload(app());
        $this->artisan('migrate', ['--force' => true]);
        Http::preventStrayRequests();
    }

    protected function fakePanel(string $version): FakePterodactylPanel
    {
        $panel = new FakePterodactylPanel($version);
        $panel->fake();

        return $panel;
    }

    protected function panelServer(string $type = 'pterodactyl', string $hostname = 'panel.test'): Server
    {
        return Server::factory()->create(['type' => $type, 'hostname' => $hostname, 'port' => 443, 'status' => 'active', 'password' => 'app-key', 'username' => 'client-key']);
    }

    protected function pterodactylService(array $eggs = ['12'.PterodactylConfig::DELIMITER.'3'], array $customer = [], ?Server $server = null): Service
    {
        Customer::factory()->create($customer);
        $server ??= $this->panelServer();
        $service = Service::factory()->create(['type' => 'pterodactyl', 'server_id' => $server->id, 'customer_id' => Customer::latest('id')->first()->id, 'data' => ['source' => 'test']]);
        // ServiceFactory always creates its own server on localhost, which would answer outside the fake panel.
        Server::whereKeyNot($server->id)->where('hostname', 'localhost')->delete();
        $service->product->update(['type' => 'pterodactyl']);
        PterodactylConfig::insert(['product_id' => $service->product_id, 'server_id' => $server->id, 'location_id' => 1, 'memory' => 1, 'disk' => 1, 'io' => 500, 'cpu' => 100, 'swap' => 0, 'databases' => 0, 'allocations' => 1, 'backups' => 0, 'dedicated_ip' => false, 'oom_kill' => false, 'eggs' => json_encode($eggs), 'server_name' => 'srv', 'server_description' => '%service_expiration%']);

        return $service->fresh();
    }

    protected function assertPagesNeverExceedTheLimit(): void
    {
        Http::assertNotSent(function (Request $request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            return (int) ($query['per_page'] ?? 0) > 100;
        });
    }

    protected function assertNoNestRouteCalled(): void
    {
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/api/application/nests'));
    }

    protected function requestsTo(string $method, string $pathPattern): int
    {
        return Http::recorded(fn (Request $request) => $request->method() === $method && preg_match($pathPattern, parse_url($request->url(), PHP_URL_PATH)))->count();
    }
}
