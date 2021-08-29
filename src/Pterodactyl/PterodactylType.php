<?php
namespace App\Pterodactyl;

use ClientX\Product\ProductTypeInterface;

class PterodactylType implements ProductTypeInterface
{

    public function getName(): string
    {
        return "pterodactyl";
    }

    public function getTitle(): string
    {
        return "Pterodactyl";
    }
    public function getConfigPath(): string
    {
        return "pterodactyl.config";
    }

    public function getData(): ?string
    {
        return PterodactylData::class;
    }
}
