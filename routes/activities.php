<?php

use App\Http\Controllers\Api\MarketplaceClient\Activities\ModuleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Activities module routes (bilete.online)
|--------------------------------------------------------------------------
|
| Locations, access tickets, experiences and packages. Loaded from
| bootstrap/app.php with the `api` middleware group and the /api prefix, like
| routes/api.php.
|
| Every route here must stay behind `marketplace.microservice:activities-module`:
| only marketplaces with that microservice active (today: bilete.online) can
| reach this code. Ambilet and every other marketplace get a 403 before any
| controller runs. New endpoints for the module go in this file, not in
| routes/api.php.
|
*/

Route::prefix('marketplace-client')
    ->middleware(['throttle:120,1', 'marketplace.auth', 'marketplace.microservice:activities-module'])
    ->name('api.marketplace-client.activities-module.')
    ->group(function () {
        Route::get('/activities-module/status', [ModuleController::class, 'status'])
            ->name('status');
    });
