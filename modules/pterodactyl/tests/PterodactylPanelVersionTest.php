<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

require_once __DIR__.'/Fixtures/FakePterodactylPanel.php';
require_once __DIR__.'/Fixtures/InteractsWithFakePanel.php';

use App\Modules\Pterodactyl\Api\NestPanelApi;
use App\Modules\Pterodactyl\Api\PanelApi;
use App\Modules\Pterodactyl\Api\TagPanelApi;
use App\Modules\Pterodactyl\PterodactylConfig;
use App\Modules\Pterodactyl\PterodactylServerType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Modules\Pterodactyl\Fixtures\InteractsWithFakePanel;
use Tests\TestCase;

class PterodactylPanelVersionTest extends TestCase
{
    use InteractsWithFakePanel;
    use RefreshDatabase;

    private const D = '---------';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootPterodactylModule();
        Cache::flush();
    }

    public static function versions(): array
    {
        return ['panel 1.x' => ['1'], 'panel 2.x' => ['2']];
    }

    public function test_it_uses_eggs_and_tags_on_a_panel_that_exposes_tags(): void
    {
        $this->fakePanel('2');

        $this->assertInstanceOf(TagPanelApi::class, PanelApi::for($this->panelServer()));
    }

    public function test_it_uses_nests_on_a_panel_without_tags(): void
    {
        $this->fakePanel('1');

        $this->assertInstanceOf(NestPanelApi::class, PanelApi::for($this->panelServer()));
    }

    public function test_it_keeps_nests_for_wisp_without_probing(): void
    {
        Http::fake();

        $this->assertInstanceOf(NestPanelApi::class, PanelApi::for($this->panelServer('wisp')));
        Http::assertNothingSent();
    }

    public function test_it_probes_each_panel_once_then_remembers_the_answer(): void
    {
        $this->fakePanel('2');
        $server = $this->panelServer();

        PanelApi::for($server);
        PanelApi::for($server->fresh());

        $this->assertSame(1, $this->requestsTo('GET', '#/tags$#'));
    }

    public function test_it_probes_again_after_an_inconclusive_answer(): void
    {
        Http::fake(['panel.test/*' => Http::sequence()->pushStatus(500)->push(['object' => 'list', 'data' => []])]);
        $server = $this->panelServer();

        $first = PanelApi::for($server);
        $second = PanelApi::for($server);

        $this->assertInstanceOf(NestPanelApi::class, $first);
        $this->assertInstanceOf(TagPanelApi::class, $second);
    }

    public function test_it_probes_again_when_the_panel_address_changes(): void
    {
        $this->fakePanel('1');
        $server = $this->panelServer();
        PanelApi::for($server);

        (new \Tests\Modules\Pterodactyl\Fixtures\FakePterodactylPanel('2'))->fake('upgraded.test');
        $server->update(['hostname' => 'upgraded.test']);

        $this->assertInstanceOf(TagPanelApi::class, PanelApi::for($server->fresh()));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('versions')]
    public function test_connection_test_succeeds_on_both_versions(string $version): void
    {
        $this->fakePanel($version);
        $server = $this->panelServer();

        $result = (new PterodactylServerType)->testConnection(['hostname' => $server->hostname, 'port' => 443, 'password' => 'app-key', 'username' => 'client-key']);

        $this->assertTrue($result->successful(), $result->toString());
        if ($version === '2') {
            $this->assertNoNestRouteCalled();
        }
    }

    public function test_product_configuration_lists_2_x_eggs_with_their_tags(): void
    {
        $this->fakePanel('2');
        $this->panelServer();

        $eggs = $this->fetchEggs();

        $this->assertSame(['5'.self::D.'0' => 'Vanilla Minecraft (Minecraft)', '12'.self::D.'0' => 'Mumble Server (Voice Servers, Sandbox)'], $eggs);
        $this->assertNoNestRouteCalled();
    }

    public function test_product_configuration_keeps_the_former_nest_of_a_migrated_egg(): void
    {
        $panel = $this->fakePanel('2');
        $panel->tags[4]['legacy_nest_id'] = 3;
        $this->panelServer();

        $this->assertArrayHasKey('12'.self::D.'3', $this->fetchEggs());
    }

    public function test_product_configuration_lists_1_x_eggs_by_nest(): void
    {
        $this->fakePanel('1');
        $this->panelServer();

        $this->assertSame(['5'.self::D.'1' => 'Vanilla Minecraft (Minecraft)', '12'.self::D.'3' => 'Mumble Server (Voice Servers)'], $this->fetchEggs());
    }

    public function test_product_configuration_reads_every_page_of_eggs(): void
    {
        $panel = $this->fakePanel('2');
        for ($id = 100; $id < 250; $id++) {
            $panel->addEgg($id, "Egg {$id}", 1, [1]);
        }
        $this->panelServer();

        $this->assertCount(152, $this->fetchEggs());
        $this->assertPagesNeverExceedTheLimit();
    }

    public function test_one_unreachable_panel_does_not_empty_the_egg_list(): void
    {
        $panel = new \Tests\Modules\Pterodactyl\Fixtures\FakePterodactylPanel('2');
        Http::fake(['down.test/*' => Http::failedConnection(), 'panel.test/*' => fn ($request) => $panel->handle($request)]);
        $this->panelServer('pterodactyl', 'down.test');
        $this->panelServer();

        $eggs = $this->fetchEggs();

        $this->assertCount(2, $eggs);
        $this->assertNotNull(session('error'));
    }

    public function test_an_empty_egg_list_is_not_kept_in_cache(): void
    {
        $panel = new \Tests\Modules\Pterodactyl\Fixtures\FakePterodactylPanel('2');
        $broken = true;
        Http::fake(['panel.test/*' => function ($request) use ($panel, &$broken) {
            return $broken && str_contains($request->url(), '/eggs') ? Http::response(['errors' => [['code' => 'AccessDeniedHttpException', 'detail' => 'denied']]], 403) : $panel->handle($request);
        }]);
        $this->panelServer();

        $this->assertSame([], $this->fetchEggs());
        $broken = false;

        $this->assertCount(2, $this->fetchEggs());
    }

    public function test_saved_1_x_egg_keys_stay_selected_once_the_panel_runs_2_x(): void
    {
        $this->fakePanel('2');
        $service = $this->pterodactylService(['12'.self::D.'3']);

        $data = (new PterodactylConfig)->render($service->product)->getData();

        $this->assertSame(['12'.self::D.'3'], $data['currenteggs']);
        $this->assertArrayHasKey('12'.self::D.'3', $data['eggs']);
        $this->assertArrayNotHasKey('12'.self::D.'0', $data['eggs']);
    }

    private function fetchEggs(): array
    {
        $config = new PterodactylConfig;

        return Closure::bind(fn () => $this->fetchEggs(), $config, $config)();
    }
}
