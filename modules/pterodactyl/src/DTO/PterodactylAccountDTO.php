<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

namespace App\Modules\Pterodactyl\DTO;

use App\Exceptions\ServiceDeliveryException;
use App\Models\Account\Customer;
use App\Models\Provisioning\Server;
use App\Models\Provisioning\Service;
use App\Modules\Pterodactyl\Http;

class PterodactylAccountDTO
{
    const ENDPOINT = 'users';

    const PER_PAGE = Http::PER_PAGE;

    private const MAX_EXTERNAL_ID_ATTEMPTS = 20;

    public int $id;

    public ?string $external_id = null;

    public string $firstname;

    public string $lastname;

    public ?string $username = null;

    public string $email;

    public ?string $password = null;

    public bool $wasCreated = false;

    public function __construct(\stdClass $attributes, bool $wasCreated = false, ?string $password = null)
    {
        $this->id = (int) $attributes->id;
        $this->external_id = $attributes->external_id;
        $this->firstname = $attributes->first_name;
        $this->lastname = $attributes->last_name;
        $this->username = $attributes->username ?? null;
        $this->email = $attributes->email;
        $this->wasCreated = $wasCreated;
        $this->password = $password;
    }

    public static function createAccount(Customer $customer, Server $server, Service $service)
    {
        // Same id sequence as before (padding included): accounts created by older versions keep matching.
        $externalId = 'CLIENTXCMS-'.str_pad($customer->id, 5);
        for ($i = 0; ; $i++) {
            $status = Http::callApi($server, self::ENDPOINT.'/external/'.$externalId)->status();
            if ($status == 404) {
                break;
            }
            if ($status != 200 || $i >= self::MAX_EXTERNAL_ID_ATTEMPTS) {
                throw new ServiceDeliveryException("Error while looking for a free pterodactyl external id (status {$status})", $service, $status ?: 500);
            }
            $externalId = "CLIENTXCMS$i-".str_pad($customer->id, 5);
        }
        $password = \Str::random(16);
        $data = [
            'email' => $customer->email,
            'username' => \Str::slug($customer->firstname.'-'.$customer->lastname).'-'.$customer->id,
            'first_name' => $customer->firstname,
            'last_name' => $customer->lastname,
            'password' => $password,
            'root_admin' => false,
            'external_id' => $externalId,
        ];
        // Wisp server type does not require username
        if ($server->type == 'wisp') {
            unset($data['username']);
        }
        $response = Http::callApi($server, self::ENDPOINT, $data, 'POST');
        if ($response->successful()) {
            return new self($response->toJson()->attributes, true, $password);
        }
        throw new ServiceDeliveryException('Error while creating pterodactyl user : '.json_encode($response->toJson()), $service, $response->status());
    }

    public function resetPassword(Server $server, Service $service, ?string $password = null)
    {
        if ($password == null) {
            $password = \Str::random(16);
        }
        $request = Http::callApi($server, self::ENDPOINT.'/'.$this->id);
        if ($request->status() != 200) {
            throw new ServiceDeliveryException('Error while getting user', $service, $request->status());
        }
        $data = $request->toJson();
        $data = [
            'email' => $data->attributes->email,
            'username' => $data->attributes->username ?? null,
            'first_name' => $data->attributes->first_name,
            'last_name' => $data->attributes->last_name,
            'password' => $password,
        ];
        if ($server->type == 'wisp') {
            unset($data['username']);
        }
        $updateResult = Http::callApi($server, self::ENDPOINT.'/'.$this->id, $data, 'PATCH');
        if ($updateResult->status() != 200) {
            throw new ServiceDeliveryException('Error while updating user', $service, $updateResult->status());
        }
        $this->password = $password;

        return $this;
    }

    public static function getUserAccount(Customer $customer, Server $server, Service $service): PterodactylAccountDTO
    {
        $result = null;
        // The panel filter is a partial match: only an exact, case-insensitive address identifies the account.
        foreach (Http::listAll($server, self::ENDPOINT, ['filter' => ['email' => $customer->email]]) as $value) {
            if (strtolower($value->attributes->email) === strtolower($customer->email)) {
                $result = $value->attributes;
                break;
            }
        }
        if ($result != null) {
            if (! $result->root_admin && ! PterodactylServerDTO::ownsAnyServer(new PterodactylAccountDTO($result), $server)) {
                return (new PterodactylAccountDTO($result, false, null))->resetPassword($server, $service);
            }

            return new PterodactylAccountDTO($result);
        }

        return PterodactylAccountDTO::createAccount($customer, $server, $service);
    }
}
