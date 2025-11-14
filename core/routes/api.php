<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Public\SeatingController;

Route::get('/v1/public/events', function () {
    return response()->json([
        ['id'=>1,'slug'=>'concert-demo','title'=>'Concert Demo'],
        ['id'=>2,'slug'=>'premiera-odeon','title'=>'Premiera Odeon'],
    ]);
});

/*
|--------------------------------------------------------------------------
| Seating API Routes - Public
|--------------------------------------------------------------------------
|
| Public-facing seating APIs with rate limiting and session management
|
*/

Route::prefix('public')->middleware(['seating.session'])->group(function () {

    // Get seating layout and metadata for an event
    Route::get('/events/{eventId}/seating', [SeatingController::class, 'getSeating'])
        ->name('api.seating.get-layout')
        ->middleware('throttle:seating_query');

    // Get seat availability and pricing
    Route::get('/events/{eventId}/seats', [SeatingController::class, 'getSeats'])
        ->name('api.seating.get-seats')
        ->middleware('throttle:seating_query');

    // Hold seats for session (10 minute TTL)
    Route::post('/seats/hold', [SeatingController::class, 'holdSeats'])
        ->name('api.seating.hold')
        ->middleware('throttle:seating_hold');

    // Release held seats
    Route::delete('/seats/hold', [SeatingController::class, 'releaseSeats'])
        ->name('api.seating.release')
        ->middleware('throttle:seating_release');

    // Confirm purchase (mark as sold)
    Route::post('/seats/confirm', [SeatingController::class, 'confirmPurchase'])
        ->name('api.seating.confirm')
        ->middleware('throttle:seating_confirm');

    // Get session's current holds
    Route::get('/seats/holds', [SeatingController::class, 'getSessionHolds'])
        ->name('api.seating.get-holds')
        ->middleware('throttle:seating_query');
});
