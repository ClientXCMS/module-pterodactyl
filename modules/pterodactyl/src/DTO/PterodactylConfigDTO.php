<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

namespace App\Modules\Pterodactyl\DTO;

use App\Models\Account\Customer;
use App\Models\Provisioning\Service;

class PterodactylConfigDTO
{
    public Service $service;

    public Customer $customer;

    public int $pterodactylId;

    public int $egg;

    public int $nest;

    public string $image;

    public string $startup;

    public array $environment;

    public function __construct(Service $service, Customer $customer, int $pterodactylId, int $egg, int $nest, string $image, string $startup, array $environment)
    {
        $this->service = $service;
        $this->customer = $customer;
        $this->pterodactylId = $pterodactylId;
        $this->egg = $egg;
        $this->nest = $nest;
        $this->image = $image;
        $this->startup = $startup;
        $this->environment = $environment;
        if ($service->configoptions()->count() == 0) {
            return;
        }
        foreach ($this->environment as $key => $value) {
            $key = strtolower($key);
            $value = $service->getOptionValue($service->type.'_custom_'.$key);
            if ($value) {
                $this->environment[strtoupper($key)] = $value;
            }
        }
    }
}
