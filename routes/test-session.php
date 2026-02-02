<?php

// TEST ROUTE - Add to routes/web.php temporarily

Route::get('/test-session', function() {
    // Set a session value
    session(['test_key' => 'test_value_' . time()]);

    return response()->json([
        'session_id' => session()->getId(),
        'test_key' => session('test_key'),
        'all_session' => session()->all(),
        'has_cookie' => request()->hasCookie(config('session.cookie')),
        'cookie_name' => config('session.cookie'),
        'session_driver' => config('session.driver'),
    ])->withCookie(cookie('manual-test', 'manual-value', 120));
});

Route::get('/test-session-read', function() {
    return response()->json([
        'session_id' => session()->getId(),
        'test_key' => session('test_key'),
        'all_session' => session()->all(),
        'has_cookie' => request()->hasCookie(config('session.cookie')),
        'manual_test_cookie' => request()->cookie('manual-test'),
    ]);
});
