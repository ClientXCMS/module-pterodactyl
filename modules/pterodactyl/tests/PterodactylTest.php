<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

use App\Models\Provisioning\Server;
use Tests\TestCase;

class PterodactylTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;
    /*
    public function test_pterodactyl_get_user()
    {
        $this->seed(\Database\Seeders\ModuleSeeder::class);
        app('modulemanager')->autoload(app());

        $this->seed(ServerSeeder::class);
        $server = Server::where('type', 'pterodactyl')->first();
        $customer = \App\Models\Account\Customer::factory()->create();
        $this->updateAccountOnPterodactyl($customer->email, 413, $server);
        $service = new Service();
        $service->id = 0;
        $account = app(PterodactylServerType::class)->getUserAccount($customer, $server, $service);
        $this->assertInstanceOf(PterodactylAccountDTO::class, $account, $service);
    }

    public function test_pterodactyl_create_user_if_not_exist()
    {
        $this->seed(\Database\Seeders\ModuleSeeder::class);
        app('modulemanager')->autoload(app());

        $this->seed(ServerSeeder::class);
        $server = Server::where('type', 'pterodactyl')->first();
        $customer = \App\Models\Account\Customer::factory()->create();
        $this->updateAccountOnPterodactyl($customer->email . 'bis', 413, $server);
        $service = new Service();
        $service->id = 0;
        $account = app(PterodactylServerType::class)->getUserAccount($customer, $server, $service);
        $this->assertInstanceOf(PterodactylAccountDTO::class, $account);
        $this->assertEquals($customer->email, $account->email);
        $this->deleteAccountOnPterodactyl($account->id, $server);
        $this->assertEquals(true, $account->wasCreated);
    }

    public function test_pterodactyl_reset_password_if_user_cannot_have_servers()
    {
        $this->seed(\Database\Seeders\ModuleSeeder::class);
        app('modulemanager')->autoload(app());

        $this->seed(ServerSeeder::class);
        $server = Server::where('type', 'pterodactyl')->first();
        $customer = \App\Models\Account\Customer::factory()->create();
        $this->updateAccountOnPterodactyl($customer->email, 413, $server);
        $service = new Service();
        $service->id = 0;
        $account = app(PterodactylServerType::class)->getUserAccount($customer, $server, $service);
        $this->assertInstanceOf(PterodactylAccountDTO::class, $account);
        $this->assertEquals($customer->email, $account->email);
        $this->assertEquals(false, $account->wasCreated);
        $this->assertNotNull($account->password);
        $this->deleteAccountOnPterodactyl($account->id, $server);
        dump($account->email, $account->password);
    }

    public function test_pterodactyl_create_service()
    {

        $this->seed(\Database\Seeders\ModuleSeeder::class);
        app('modulemanager')->autoload(app());

        $this->seed(ServerSeeder::class);
        $server = Server::where('type', 'pterodactyl')->first();

        $this->seed(EmailTemplateSeeder::class);
        $this->seed(StoreSeeder::class);

        Customer::factory(20)->create();
        $this->createProductModel();
        $invoiceItem = InvoiceItem::factory()->create();
        $invoice = $invoiceItem->invoice;
        if ($invoice->status == 'paid') {
            $invoice->update(['state' => 'completed']);
        }
        $invoice->complete();
        $this->assertDatabaseCount('services', 1);
        $service = Service::first();
        $this->deleteServerOnPterodactyl($service->id, $server);
        $statechange = app(PterodactylServerType::class)->createAccount($service);
        $this->assertEquals(true, $statechange->success);
        $this->assertInstanceOf(ServiceStateChangeDTO::class, $statechange);
        $this->assertEquals('Server created', $statechange->message);
        $this->deleteServerOnPterodactyl($service->id, $server);
    }
*/

    public function updateAccountOnPterodactyl(string $email, int $id, Server $server)
    {
        $request = \App\Modules\Pterodactyl\Http::callApi($server, 'users/'.$id);
        $data = $request->toJson();
        $response = \App\Modules\Pterodactyl\Http::callApi($server, 'users/'.$id, [
            'email' => $email,
            'username' => $data->attributes->username,
            'first_name' => $data->attributes->first_name,
            'last_name' => $data->attributes->last_name,
        ], 'PATCH');
        if ($response->successful()) {
            return true;
        }
        throw new \Exception('Error while updating user');
    }

    private function deleteAccountOnPterodactyl(int $id, Server $server)
    {
        \App\Modules\Pterodactyl\Http::callApi($server, 'users/'.$id, [], 'DELETE');
    }

    private function deleteServerOnPterodactyl(int $serviceId, Server $server)
    {
        $fetch = \App\Modules\Pterodactyl\Http::callApi($server, 'servers/external/'.$serviceId);
        if ($fetch->successful()) {
            $serverId = $fetch->toJson()->attributes->id;
            \App\Modules\Pterodactyl\Http::callApi($server, 'servers/'.$serverId, [], 'DELETE');
        }
    }
}
