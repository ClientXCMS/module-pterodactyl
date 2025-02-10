<?php
/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */
namespace App\Modules\Pterodactyl\Models;

use App\Models\Account\Customer;
use App\Models\Provisioning\Server;
use App\Models\Provisioning\Service;
use App\Models\Store\Product;
use App\Modules\Pterodactyl\DTO\PterodactylConfigDTO;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PterodactylConfig extends Model
{
    use HasFactory;

    const DELIMITER = "---------";

    protected $fillable = [
        'product_id',
        'memory',
        'disk',
        'io',
        'cpu',
        'node_id',
        'location_id',
        'server_id',
        'backups',
        'image',
        'startup',
        'dedicated_ip',
        'oom_kill',
        'server_name',
        'server_description',
        'swap',
        'port_range',
        'databases',
        'allocations',
        'eggs',
    ];

    protected $casts = [
        'eggs' => 'array',
    ];

    protected $attributes = [
        'dedicated_ip' => false,
        'oom_kill' => false,
        'memory' => 10,
        'disk' => 10,
        'servers' => 3,
        'io' => 500,
        'cpu' => 1,
        'swap' => 0,
        'databases' => 0,
        'allocations' => 0,
        'backups' => 0,
        'server_name' => '%owner_username%\'s server',
        'server_description' => '%service_expiration%',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function toRequest(Service $service, PterodactylConfigDTO $dto)
    {
        $portRange = isset($this->port_range) ? explode(',', $this->port_range) : [];
        $portRange = collect($portRange)->map(function ($range) {
            return (string) $range;
        })->toArray();
        return [
            'name' => $this->placeholder($dto->service, $dto->customer, $this->server_name ?? '%owner_username%\'s server'),
            'description' => $this->placeholder($dto->service, $dto->customer, $this->server_description ?? '%service_expiration%'),
            'user' => $dto->pterodactylId,
            'nest' => $dto->nest,
            'egg' => $dto->egg,
            'docker_image' => $this->image ?? $dto->image,
            'startup' => $this->startup ?? $dto->startup,
            'limits' => [
                'memory' => (int)(($this->memory + $service->getOptionValue('additional_memory', 0)) * 1024),
                'swap' => $this->swap + $service->getOptionValue('additional_swap', 0),
                'disk' => (int)(($this->disk + $service->getOptionValue('additional_disk', 0)) * 1024),
                'io' => $this->io + $service->getOptionValue('additional_io', 0),
                'cpu' => ($this->cpu  + $service->getOptionValue('additional_cpu', 0)),
            ],
            'feature_limits' => [
                'databases' => $this->databases + $service->getOptionValue('additional_databases', 0),
                'allocations' => $this->allocations + $service->getOptionValue('additional_allocations', 0),
                'backups' => $this->backups + $service->getOptionValue('additional_backups', 0),
            ],
            'deploy' => [
                'locations' => [$service->getOptionValue($service->type . '_location_id', $this->location_id)],
                'dedicated_ip' => (int)($service->getOptionValue($service->type . '_dedicated_ip', $this->dedicated_ip ? 'true' : 'false') == 'true'),
                'port_range' => $portRange,
            ],
            'environment' => $dto->environment,
            'start_on_completion' => true,
            'external_id' => (string)$dto->service->id,
        ];
    }

    private function placeholder(Service $service, Customer $customer, ?string $message = null)
    {
        if ($message == null) return null;
        $context = [
            'owner_email' => $customer->email,
            'owner_username' => $customer->firstname . ' ' . $customer->lastname,
            'owner_firstname' => $customer->firstname,
            'owner_lastname' => $customer->lastname,
            'product_name' => $this->product->name,
            'service_id' => $service->id,
            'service_expiration' => $service->expires_at != null ? $service->expires_at->format('d/m/y') : 'Exp: None',
        ];
        return str_replace('%', '', str_replace(array_keys($context), array_values($context), $message));
    }

}
