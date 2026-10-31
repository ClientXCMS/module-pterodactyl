<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

require_once __DIR__.'/Fixtures/FakePterodactylPanel.php';
require_once __DIR__.'/Fixtures/InteractsWithFakePanel.php';

use App\Exceptions\ServiceDeliveryException;
use App\Modules\Pterodactyl\DTO\EggDTO;
use App\Modules\Pterodactyl\DTO\PterodactylAccountDTO;
use App\Modules\Pterodactyl\DTO\PterodactylServerDTO;
use App\Modules\Pterodactyl\PterodactylConfig;
use App\Modules\Pterodactyl\PterodactylServerType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Modules\Pterodactyl\Fixtures\InteractsWithFakePanel;
use Tests\TestCase;

class PterodactylRobustnessTest extends TestCase
{
    use InteractsWithFakePanel;
    use RefreshDatabase;

    private const D = '---------';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootPterodactylModule();
        Cache::flush();
        Notification::fake();
    }

    public static function versions(): array
    {
        return ['panel 1.x' => ['1'], 'panel 2.x' => ['2']];
    }

    public function test_a_taken_external_id_moves_on_to_the_next_one_in_the_existing_format(): void
    {
        $panel = $this->fakePanel('2');
        $service = $this->pterodactylService(customer: ['email' => 'new@example.com']);
        $taken = $panel->addUser('someone@example.com');
        $panel->users[$taken]['external_id'] = 'CLIENTXCMS-'.str_pad($service->customer->id, 5);

        $account = PterodactylAccountDTO::getUserAccount($service->customer, $service->server, $service);

        $this->assertSame('CLIENTXCMS0-'.str_pad($service->customer->id, 5), $account->external_id);
    }

    public function test_an_external_id_lookup_refused_by_the_panel_stops_the_delivery_at_once(): void
    {
        $panel = $this->fakePanel('2');
        $panel->forcedStatus['#^users/external/#'] = 403;
        $service = $this->pterodactylService();

        try {
            PterodactylAccountDTO::getUserAccount($service->customer, $service->server, $service);
            $this->fail('The delivery went on although the panel refused the lookup.');
        } catch (ServiceDeliveryException) {
            $this->assertSame(1, $this->requestsTo('GET', '#/users/external/#'));
            $this->assertSame(0, $this->requestsTo('POST', '#/users$#'));
        }
    }

    public function test_the_external_id_search_is_bounded_when_every_id_seems_taken(): void
    {
        $panel = $this->fakePanel('2');
        $panel->forcedStatus['#^users/external/#'] = 200;
        $service = $this->pterodactylService();

        $this->expectException(ServiceDeliveryException::class);
        try {
            PterodactylAccountDTO::getUserAccount($service->customer, $service->server, $service);
        } finally {
            $this->assertLessThanOrEqual(21, $this->requestsTo('GET', '#/users/external/#'));
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('versions')]
    public function test_finding_an_account_does_not_read_the_whole_server_list(string $version): void
    {
        $panel = $this->fakePanel($version);
        $panel->addServers(150, $panel->addUser('other@example.com'));
        $panel->addServer($panel->addUser('owner@example.com'));
        $service = $this->pterodactylService(customer: ['email' => 'owner@example.com']);

        $account = PterodactylAccountDTO::getUserAccount($service->customer, $service->server, $service);

        $this->assertNull($account->password);
        $this->assertSame(0, $this->requestsTo('GET', '#/api/application/servers$#'));
    }

    public function test_it_falls_back_to_the_server_list_when_the_key_cannot_read_included_servers(): void
    {
        $panel = $this->fakePanel('1');
        $panel->includeServersAuthorized = false;
        $panel->addServers(120, $panel->addUser('other@example.com'));
        $panel->addServer($panel->addUser('owner@example.com'));
        $service = $this->pterodactylService(customer: ['email' => 'owner@example.com']);

        $account = PterodactylAccountDTO::getUserAccount($service->customer, $service->server, $service);

        $this->assertNull($account->password);
        $this->assertSame(0, $this->requestsTo('PATCH', '#/users/\d+$#'));
    }

    public function test_a_customer_without_server_still_gets_a_fresh_password(): void
    {
        $panel = $this->fakePanel('2');
        $panel->addUser('owner@example.com');
        $service = $this->pterodactylService(customer: ['email' => 'owner@example.com']);

        $account = PterodactylAccountDTO::getUserAccount($service->customer, $service->server, $service);

        $this->assertNotNull($account->password);
        $this->assertSame(1, $this->requestsTo('PATCH', '#/users/\d+$#'));
    }

    public function test_product_configuration_only_offers_servers_of_its_own_type(): void
    {
        $wisp = $this->panelServer('wisp', 'wisp.test');
        $pterodactyl = $this->panelServer();
        $pterodactylConfig = new PterodactylConfig;
        $wispConfig = new class extends PterodactylConfig
        {
            protected string $type = 'wisp';
        };

        $first = Closure::bind(fn () => $this->fetchServers(), $pterodactylConfig, PterodactylConfig::class)();
        $second = Closure::bind(fn () => $this->fetchServers(), $wispConfig, PterodactylConfig::class)();

        $this->assertSame([$pterodactyl->id], array_keys($first));
        $this->assertSame([$wisp->id], array_keys($second));
    }

    public function test_an_egg_environment_uses_defaults_and_only_the_given_overrides(): void
    {
        $egg = new EggDTO((object) ['docker_image' => 'image:1', 'startup' => './start', 'relationships' => (object) ['variables' => (object) ['data' => [
            (object) ['attributes' => (object) ['env_variable' => 'MAX_USERS', 'default_value' => '100']],
            (object) ['attributes' => (object) ['env_variable' => 'FIVEM_LICENSE', 'default_value' => '']],
        ]]]]);

        $this->assertSame(['MAX_USERS' => '100', 'FIVEM_LICENSE' => 'Change me'], $egg->environment());
        $this->assertSame(['MAX_USERS' => '20', 'FIVEM_LICENSE' => 'Change me'], $egg->environment(['MAX_USERS' => '20', 'eggId' => '5']));
        $this->assertSame(['image:1', './start'], [$egg->image(), $egg->startup()]);
    }

    public function test_changing_the_egg_keeps_a_value_saved_on_the_service(): void
    {
        $panel = $this->fakePanel('2');
        $service = $this->pterodactylService(['5'.self::D.'1', '12'.self::D.'3'], ['email' => 'owner@example.com']);
        $service->update(['data' => ['VAR_5' => 'custom']]);
        $panel->addServer($panel->addUser('owner@example.com'), (string) $service->id);

        PterodactylServerDTO::getServerFromExternalId($service->fresh())->changeEgg($service->fresh(), '5');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/startup') && $request['environment'] === ['VAR_5' => 'custom']);
    }

    public function test_an_upgrade_applies_the_same_limits_as_the_delivery(): void
    {
        $panel = $this->fakePanel('2');
        $service = $this->pterodactylService(customer: ['email' => 'owner@example.com']);
        $panel->addServer($panel->addUser('owner@example.com'), (string) $service->id);

        $result = (new PterodactylServerType)->upgradeService($service, $service->product);

        $this->assertTrue($result->success, $result->message);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/build') && $request['allocation'] === 4
            && $request['memory'] === 1024 && $request['disk'] === 1024 && $request['cpu'] === 100 && $request['io'] === 500 && $request['swap'] === 0
            && $request['feature_limits'] === ['databases' => 0, 'allocations' => 1, 'backups' => 0]);
    }

    public function test_the_upgrade_migration_drops_the_caches_left_by_version_1_4(): void
    {
        $service = $this->pterodactylService();
        $productId = $service->product_id;
        Cache::forever("pterodactyl_http_{$productId}", ['eggnames' => [], 'defaultEgg' => 'Not found']);
        Cache::forever("pterodactyl_eggs_{$productId}", ['Mumble Server' => 'Mumble Server']);
        Cache::forever("pterodactyl_egg_{$service->server_id}_Mumble Server", [null, null]);
        Cache::put('pterodactyl_eggs', [], 600);
        Cache::put('pterodactyl_locations', [], 600);
        Cache::forever("pterodactyl_cache2_http_{$productId}", ['kept']);

        (require dirname(__DIR__).'/database/migrations/2026_10_31_000000_forget_caches_left_by_pterodactyl_module_1_4.php')->up();

        foreach (["pterodactyl_http_{$productId}", "pterodactyl_eggs_{$productId}", "pterodactyl_egg_{$service->server_id}_Mumble Server", 'pterodactyl_eggs', 'pterodactyl_locations'] as $key) {
            $this->assertFalse(Cache::has($key), $key);
        }
        $this->assertSame(['kept'], Cache::get("pterodactyl_cache2_http_{$productId}"));
    }
}
