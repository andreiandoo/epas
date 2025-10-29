<?php

use Illuminate\Support\Facades\Route;

Route::get('/v1/public/events', function () {
    return response()->json([
        ['id'=>1,'slug'=>'concert-demo','title'=>'Concert Demo'],
        ['id'=>2,'slug'=>'premiera-odeon','title'=>'Premiera Odeon'],
    ]);
});
