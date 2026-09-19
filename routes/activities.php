<?php

use App\Http\Controllers\Api\MarketplaceClient\Activities\LocationsController;
use App\Http\Controllers\Api\MarketplaceClient\Activities\ModuleController;
use App\Http\Controllers\Api\MarketplaceClient\Activities\ProductsController;
use App\Http\Controllers\Api\MarketplaceClient\Activities\Organizer\BookingsController as OrganizerBookings;
use App\Http\Controllers\Api\MarketplaceClient\Activities\Organizer\LocationsController as OrganizerLocations;
use App\Http\Controllers\Api\MarketplaceClient\Activities\Organizer\ProductsController as OrganizerProducts;
use App\Http\Controllers\Api\MarketplaceClient\Activities\Organizer\UploadsController as OrganizerUploads;
use App\Http\Controllers\Api\MarketplaceClient\Activities\Organizer\PosController as OrganizerPos;
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

        // Public catalogue: locations and what they sell.
        Route::get('/activities-module/locations', [LocationsController::class, 'index'])
            ->name('locations.index');
        Route::get('/activities-module/locations/{slug}', [LocationsController::class, 'show'])
            ->where('slug', '[a-z0-9-]+')->name('locations.show');
        Route::get('/activities-module/locations/{slug}/day', [LocationsController::class, 'day'])
            ->where('slug', '[a-z0-9-]+')->name('locations.day');
        Route::get('/activities-module/locations/{slug}/calendar', [LocationsController::class, 'calendar'])
            ->where('slug', '[a-z0-9-]+')->name('locations.calendar');

        Route::get('/activities-module/products/{slug}', [ProductsController::class, 'show'])
            ->where('slug', '[a-z0-9-]+')->name('products.show');
        Route::get('/activities-module/products/{slug}/day', [ProductsController::class, 'day'])
            ->where('slug', '[a-z0-9-]+')->name('products.day');
        Route::get('/activities-module/products/{slug}/calendar', [ProductsController::class, 'calendar'])
            ->where('slug', '[a-z0-9-]+')->name('products.calendar');
    });

// Operator account: own locations, products, bookings (organizer token).
Route::prefix('marketplace-client/organizer/activities-module')
    ->middleware(['throttle:120,1', 'marketplace.auth', 'auth:sanctum', 'marketplace.microservice:activities-module'])
    ->name('api.marketplace-client.organizer.activities-module.')
    ->group(function () {
        Route::get('/meta', [OrganizerLocations::class, 'meta'])->name('meta');

        Route::get('/locations', [OrganizerLocations::class, 'index'])->name('locations.index');
        Route::post('/locations', [OrganizerLocations::class, 'store'])->name('locations.store');
        Route::get('/locations/{id}', [OrganizerLocations::class, 'show'])->whereNumber('id')->name('locations.show');
        Route::put('/locations/{id}', [OrganizerLocations::class, 'update'])->whereNumber('id')->name('locations.update');
        Route::post('/locations/{id}/submit', [OrganizerLocations::class, 'submit'])->whereNumber('id')->name('locations.submit');
        Route::post('/locations/{id}/publish', [OrganizerLocations::class, 'publish'])->whereNumber('id')->name('locations.publish');
        Route::delete('/locations/{id}', [OrganizerLocations::class, 'destroy'])->whereNumber('id')->name('locations.destroy');

        Route::get('/products', [OrganizerProducts::class, 'index'])->name('products.index');
        Route::post('/products', [OrganizerProducts::class, 'store'])->name('products.store');
        Route::get('/products/{id}', [OrganizerProducts::class, 'show'])->whereNumber('id')->name('products.show');
        Route::put('/products/{id}', [OrganizerProducts::class, 'update'])->whereNumber('id')->name('products.update');
        Route::post('/products/{id}/submit', [OrganizerProducts::class, 'submit'])->whereNumber('id')->name('products.submit');
        Route::post('/products/{id}/publish', [OrganizerProducts::class, 'publish'])->whereNumber('id')->name('products.publish');
        Route::post('/products/{id}/duplicate', [OrganizerProducts::class, 'duplicate'])->whereNumber('id')->name('products.duplicate');
        Route::delete('/products/{id}', [OrganizerProducts::class, 'destroy'])->whereNumber('id')->name('products.destroy');

        Route::get('/bookings', [OrganizerBookings::class, 'index'])->name('bookings.index');
        Route::get('/bookings/day', [OrganizerBookings::class, 'day'])->name('bookings.day');
        Route::get('/bookings/export', [OrganizerBookings::class, 'export'])->name('bookings.export');
        Route::post('/bookings/{id}/no-show', [OrganizerBookings::class, 'noShow'])->whereNumber('id')->name('bookings.no-show');
        Route::get('/summary', [OrganizerBookings::class, 'summary'])->name('summary');

        Route::post('/uploads', [OrganizerUploads::class, 'store'])->name('uploads.store');

        // The cash desk (POS) at a location: products with POS prices, what is left, cash sessions, sales
        Route::get('/pos/catalog', [OrganizerPos::class, 'catalog'])->name('pos.catalog');
        Route::get('/pos/day', [OrganizerPos::class, 'day'])->name('pos.day');
        Route::get('/pos/session', [OrganizerPos::class, 'session'])->name('pos.session');
        Route::post('/pos/session', [OrganizerPos::class, 'open'])->name('pos.open');
        Route::post('/pos/session/{id}/close', [OrganizerPos::class, 'close'])->whereNumber('id')->name('pos.close');
        Route::post('/pos/sale', [OrganizerPos::class, 'sale'])->name('pos.sale');
        Route::get('/pos/sales', [OrganizerPos::class, 'sales'])->name('pos.sales');
    });
