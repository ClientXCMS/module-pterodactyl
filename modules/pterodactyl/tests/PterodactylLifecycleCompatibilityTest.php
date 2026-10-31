<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

require_once __DIR__.'/Fixtures/FakePterodactylPanel.php';
require_once __DIR__.'/Fixtures/InteractsWithFakePanel.php';

use App\DTO\Store\ProductDataDTO;
use App\Exceptions\ExternalApiException;
use App\Modules\Pterodactyl\Api\PanelApi;
use App\Modules\Pterodactyl\DTO\PterodactylAccountDTO;
use App\Modules\Pterodactyl\DTO\PterodactylServerDTO;
use App\Modules\Pterodactyl\PterodactylData;
use App\Modules\Pterodactyl\PterodactylPanel;
use App\Modules\Pterodactyl\PterodactylServerType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Modules\Pterodactyl\Fixtures\FakePterodactylPanel;
use Tests\Modules\Pterodactyl\Fixtures\InteractsWithFakePanel;
use Tests\TestCase;

class PterodactylLifecycleCompatibilityTest extends TestCase
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

    #[\PHPUnit\Framework\Attributes\DataProvider('versions')]
    public function test_it_delivers_a_server_from_a_configuration_saved_under_1_x(string $version): void
    {
        $this->fakePanel($version);
        $service = $this->pterodactylService(['12'.self::D.'3']);

        $result = (new PterodactylServerType)->createAccount($service);

        $this->assertTrue($result->success, $result->message);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), '/api/application/servers')
            && $request['egg'] === 12 && $request['docker_image'] === 'image:12' && $request['environment'] === ['VAR_12' => 'default']);
        $this->assertPagesNeverExceedTheLimit();
        if ($version === '2') {
            $this->assertNoNestRouteCalled();
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('versions')]
    public function test_it_delivers_the_egg_chosen_at_order_time(string $version): void
    {
        $this->fakePanel($version);
        $service = $this->pterodactylService(['5'.self::D.'1', '12'.self::D.'3']);
        $service->update(['data' => (new PterodactylData)->parameters(new ProductDataDTO($service->product, [], ['eggname' => 'Mumble Server']))]);

        $result = (new PterodactylServerType)->createAccount($service->fresh());

        $this->assertTrue($result->success, $result->message);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), '/servers') && $request['egg'] === 12);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('versions')]
    public function test_it_finds_the_customer_account_beyond_the_first_hundred_matches(string $version): void
    {
        $panel = $this->fakePanel($version);
        for ($i = 0; $i < 150; $i++) {
            $panel->addUser("{$i}ab@example.com");
        }
        $expected = $panel->addUser('ab@example.com');
        $service = $this->pterodactylService(customer: ['email' => 'ab@example.com']);

        $account = PterodactylAccountDTO::getUserAccount($service->customer, $service->server, $service);

        $this->assertSame($expected, $account->id);
        $this->assertSame(0, $this->requestsTo('POST', '#/users$#'));
        $this->assertPagesNeverExceedTheLimit();
    }

    public function test_it_finds_an_address_that_contains_a_plus_sign(): void
    {
        $panel = $this->fakePanel('2');
        $expected = $panel->addUser('first+shop@example.com');
        $service = $this->pterodactylService(customer: ['email' => 'first+shop@example.com']);

        $account = PterodactylAccountDTO::getUserAccount($service->customer, $service->server, $service);

        $this->assertSame($expected, $account->id);
    }

    public function test_it_does_not_reset_the_password_of_a_customer_whose_server_is_on_a_later_page(): void
    {
        $panel = $this->fakePanel('2');
        $other = $panel->addUser('other@example.com');
        $panel->addServers(120, $other);
        $userId = $panel->addUser('owner@example.com');
        $panel->addServer($userId);
        $service = $this->pterodactylService(customer: ['email' => 'owner@example.com']);

        $account = PterodactylAccountDTO::getUserAccount($service->customer, $service->server, $service);

        $this->assertNull($account->password);
        $this->assertSame(0, $this->requestsTo('PATCH', '#/users/\d+$#'));
    }

    public function test_it_lists_every_server_exactly_once(): void
    {
        $panel = $this->fakePanel('2');
        $panel->addServers(150, $panel->addUser('owner@example.com'));

        $servers = PterodactylServerDTO::getAllServers($this->panelServer());

        $this->assertCount(150, $servers);
        $this->assertCount(150, array_unique(array_map(fn ($server) => $server->id, $servers)));
    }

    public function test_a_failed_listing_is_reported_instead_of_creating_a_duplicate_account(): void
    {
        Http::fake(['panel.test/*' => Http::response(['errors' => [['code' => 'ValidationException', 'detail' => 'The per page may not be greater than 100.']]], 422)]);
        $service = $this->pterodactylService();

        $this->expectException(ExternalApiException::class);
        try {
            PterodactylAccountDTO::getUserAccount($service->customer, $service->server, $service);
        } finally {
            $this->assertSame(0, $this->requestsTo('POST', '#/users$#'));
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('versions')]
    public function test_it_suspends_and_unsuspends_an_existing_service_on_a_busy_panel(string $version): void
    {
        $panel = $this->fakePanel($version);
        $panel->addUsers(150);
        $service = $this->pterodactylService(customer: ['email' => 'owner@example.com']);
        $serverId = $panel->addServer($panel->addUser('owner@example.com'), (string) $service->id);

        $suspended = (new PterodactylServerType)->suspendAccount($service);
        $this->assertTrue($suspended->success, $suspended->message);
        $this->assertTrue($panel->servers[$serverId]['suspended']);

        $unsuspended = (new PterodactylServerType)->unsuspendAccount($service->fresh());
        $this->assertTrue($unsuspended->success, $unsuspended->message);
        $this->assertFalse($panel->servers[$serverId]['suspended']);
        $this->assertSame(0, $this->requestsTo('POST', '#/users$#'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('versions')]
    public function test_it_changes_the_egg_of_a_server_with_a_key_saved_under_1_x(string $version): void
    {
        $panel = $this->fakePanel($version);
        $service = $this->pterodactylService(['5'.self::D.'1', '12'.self::D.'3'], ['email' => 'owner@example.com']);
        $panel->addServer($panel->addUser('owner@example.com'), (string) $service->id);

        PterodactylServerDTO::getServerFromExternalId($service)->changeEgg($service, '5');

        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH' && str_ends_with($request->url(), '/startup')
            && $request['egg'] === 5 && $request['image'] === 'image:5' && $request['startup'] === 'start-5' && $request['environment'] === ['VAR_5' => 'default']);
        $this->assertSame(1, $this->requestsTo('POST', '#/servers/\d+/reinstall$#'));
    }

    public function test_it_refuses_an_egg_that_the_offer_does_not_include(): void
    {
        $panel = $this->fakePanel('2');
        $panel->addEgg(77, 'Other game', 1, [1]);
        $service = $this->pterodactylService(['12'.self::D.'3'], ['email' => 'owner@example.com']);
        $panel->addServer($panel->addUser('owner@example.com'), (string) $service->id);

        try {
            PterodactylServerDTO::getServerFromExternalId($service)->changeEgg($service, '77');
            $this->fail('An egg outside the offer was accepted.');
        } catch (ExternalApiException) {
            $this->assertSame(0, $this->requestsTo('PATCH', '#/servers/\d+/startup$#'));
            $this->assertSame(0, $this->requestsTo('POST', '#/servers/\d+/reinstall$#'));
        }
    }

    public function test_reinstalling_the_current_egg_keeps_the_server_startup_command(): void
    {
        $panel = $this->fakePanel('2');
        $service = $this->pterodactylService(['5'.self::D.'1'], ['email' => 'owner@example.com']);
        $panel->addServer($panel->addUser('owner@example.com'), (string) $service->id, 12);

        PterodactylServerDTO::getServerFromExternalId($service)->changeEgg($service, '12');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/startup') && $request['egg'] === 12 && $request['startup'] === './run.sh');
    }

    public function test_the_reinstall_page_lists_the_offer_eggs_on_2_x(): void
    {
        $panel = $this->fakePanel('2');
        $service = $this->pterodactylService(['5'.self::D.'1', '12'.self::D.'3'], ['email' => 'owner@example.com']);
        $panel->addServer($panel->addUser('owner@example.com'), (string) $service->id);

        $view = (new PterodactylPanel('pterodactyl'))->renderReinstall($service);

        $this->assertSame([5 => 'Vanilla Minecraft', 12 => 'Mumble Server'], $view->getData()['eggs']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('versions')]
    public function test_the_order_form_resolves_saved_eggs(string $version): void
    {
        $this->fakePanel($version);
        $service = $this->pterodactylService(['5'.self::D.'1', '12'.self::D.'3']);
        $data = new PterodactylData;

        $chosen = $data->parameters(new ProductDataDTO($service->product, [], ['eggname' => 'Mumble Server']));
        $default = $data->parameters(new ProductDataDTO($service->product, [], []));

        $this->assertSame(['12', '3', 'Mumble Server'], [$chosen['eggId'], $chosen['nestId'], $chosen['eggname']]);
        $this->assertSame('Vanilla Minecraft', $default['eggname']);
        $this->assertSame(['Vanilla Minecraft' => 'Vanilla Minecraft', 'Mumble Server' => 'Mumble Server'], $data->getEggs(['5'.self::D.'1', '12'.self::D.'3'], $service->server_id, $service->product_id));
    }

    public function test_the_order_form_does_not_keep_an_empty_egg_list(): void
    {
        $panel = new FakePterodactylPanel('2');
        $broken = true;
        Http::fake(['panel.test/*' => function ($request) use ($panel, &$broken) {
            return $broken && str_contains($request->url(), '/eggs/') ? Http::response(null, 500) : $panel->handle($request);
        }]);
        $service = $this->pterodactylService(['12'.self::D.'3']);
        $data = new PterodactylData;

        $this->assertSame([], $data->getEggs(['12'.self::D.'3'], $service->server_id, $service->product_id));
        $this->assertSame([null, null], $data->getEgg(['12'.self::D.'3'], 'Mumble Server', $service->server_id));
        $broken = false;

        $this->assertSame(['Mumble Server' => 'Mumble Server'], $data->getEggs(['12'.self::D.'3'], $service->server_id, $service->product_id));
        $this->assertSame(['12', '3'], $data->getEgg(['12'.self::D.'3'], 'Mumble Server', $service->server_id));
    }

    public function test_wisp_keeps_resolving_eggs_through_their_nest(): void
    {
        $this->fakePanel('1');

        $egg = PanelApi::for($this->panelServer('wisp'))->egg(12, 3, true);

        $this->assertSame('Mumble Server', $egg->toJson()->attributes->name);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/application/nests/3/eggs/12'));
    }
}
