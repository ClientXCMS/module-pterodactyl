<?php
/*
 * This file is part of the CLIENTXCMS project.
 * This file is the property of the CLIENTXCMS association. Any unauthorized use, reproduction, or download is prohibited.
 * For more information, please consult our support: clientxcms.com/client/support.
 * Year: 2024
 */
use App\Modules\Pterodactyl\Controllers\PterodactylController;

Route::name('pterodactyl')
    ->name('pterodactyl.')
    ->prefix('pterodactyl')
    ->middleware('throttle:pterodactyl-power-actions')
    ->group(function() {
        \Route::post('/power/{service}/{power}', [PterodactylController::class, 'power'])
            ->name('power')
            ->where('power', 'start|stop|restart');
    });
