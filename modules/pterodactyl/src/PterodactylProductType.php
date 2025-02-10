<?php
/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */
namespace App\Modules\Pterodactyl;

use App\Abstracts\AbstractProductType;
use App\Contracts\Provisioning\PanelProvisioningInterface;
use App\Contracts\Provisioning\ServerTypeInterface;
use App\Contracts\Store\ProductConfigInterface;
use App\Models\Store\Product;

class PterodactylProductType extends AbstractProductType
{
    protected string $title = 'Pterodactyl';
    protected string $uuid = 'pterodactyl';
    protected string $type = self::SERVICE;

    /**
     * @inheritDoc
     */
    public function panel(): ?PanelProvisioningInterface
    {
        return new PterodactylPanel();
    }

    /**
     * @inheritDoc
     */
    public function server(): ?ServerTypeInterface
    {
        return new PterodactylServerType();
    }

    /**
     * @inheritDoc
     */
    public function data(?Product $product=null): ?\App\Contracts\Store\ProductDataInterface
    {
        return new PterodactylData();
    }

    public function config(): ?ProductConfigInterface
    {
        return new PterodactylConfig();
    }

}
