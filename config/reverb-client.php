<?php

/*
|--------------------------------------------------------------------------
| Reverb — public client config
|--------------------------------------------------------------------------
|
| Reverb's broadcasting.php config holds the *server-side* connection
| (host/port for the daemon + HTTP-API auth). The embed page's Pusher JS
| client needs DIFFERENT values: the public host through which the browser
| reaches Reverb (usually behind nginx on 443) and the optional sub-path.
|
| Reading via config() here (not env() in the controller) keeps the values
| working under `php artisan config:cache` — env() inside controllers
| returns the default once the config cache is built.
|
*/

return [

    'app_key' => env('REVERB_APP_KEY'),

    // Public host the browser uses (NOT the daemon's bind host). Falls
    // back to REVERB_HOST so single-host setups still work.
    'host' => env('REVERB_HOST_PUBLIC', env('REVERB_HOST')),

    // Public port (usually 443 when nginx terminates TLS).
    'port' => (int) env('REVERB_PORT_PUBLIC', env('REVERB_PORT', 8080)),

    // Public scheme — defaults to https since nginx terminates TLS in
    // front of Reverb. The internal REVERB_SCHEME (used by the daemon)
    // can stay 'http' for the loopback proxy.
    'scheme' => env('REVERB_SCHEME_PUBLIC', 'https'),

    // Optional sub-path when nginx routes e.g. /reverb/ → 127.0.0.1:8080.
    // Empty string = root.
    'path' => env('REVERB_PATH_PUBLIC', ''),

];
