<?php

/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */

namespace App\Modules\Pterodactyl\DTO;

use App\Modules\Pterodactyl\PterodactylResponse;

class EggDTO
{
    public function __construct(public readonly \stdClass $attributes) {}

    public static function fromResponse(?PterodactylResponse $response): ?self
    {
        $attributes = $response?->status() == 200 ? ($response->toJson()->attributes ?? null) : null;

        return $attributes instanceof \stdClass ? new self($attributes) : null;
    }

    public function image(): ?string
    {
        return $this->attributes->docker_image ?? null;
    }

    public function startup(): ?string
    {
        return $this->attributes->startup ?? null;
    }

    public function hasVariables(): bool
    {
        return isset($this->attributes->relationships->variables->data);
    }

    // Egg defaults, replaced by a value of $overrides only for a variable the egg declares.
    public function environment(array $overrides = []): array
    {
        $environment = [];
        foreach ($this->attributes->relationships->variables->data ?? [] as $variable) {
            $name = $variable->attributes->env_variable;
            $default = $variable->attributes->default_value;
            if ($name === 'FIVEM_LICENSE' && empty($default)) {
                $default = 'Change me';
            }
            $environment[$name] = $overrides[$name] ?? $default;
        }

        return $environment;
    }
}
