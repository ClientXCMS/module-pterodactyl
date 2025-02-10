<?php
/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */
namespace App\Modules\Pterodactyl;

use App\Modules\Pterodactyl\Controllers\PterodactylController;
use App\Services\Store\ProductTypeService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\View;
use RateLimiter;

class PterodactylServiceProvider extends \App\Extensions\BaseModuleServiceProvider
{
    protected string $name = "Pterodactyl";
    protected string $version = "1.0.0";
    protected string $uuid = "pterodactyl";


    public function boot(): void
    {
        RateLimiter::for('pterodactyl-power-actions', function ($job) {
            return Limit::perMinute(5)
                ->by(optional($job->user())->id ?: $job->ip());
        });
        $this->loadViews();
        $this->loadTranslations();
        $this->loadMigrations();
        $this->registerProductTypes();
        \Route::middleware('web')->group(module_path('pterodactyl', 'routes/web.php'));
    }

    public function productsTypes(): array
    {
        return [
            PterodactylProductType::class,
        ];
    }

}
