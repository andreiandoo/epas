<?php

/**
 * SECURITY FIX: CORS Configuration
 *
 * CRITICAL: Previously had allowed_origins='*' with supports_credentials=true
 * This combination is INSECURE and allows CSRF attacks from any domain.
 *
 * Now uses environment variables for allowed origins.
 * Set CORS_ALLOWED_ORIGINS in .env as comma-separated list:
 * CORS_ALLOWED_ORIGINS=https://yourdomain.com,https://app.yourdomain.com
 *
 * For development, set CORS_ALLOW_LOCALHOST=true in .env
 */

$allowedOrigins = env('CORS_ALLOWED_ORIGINS', '');
$originsArray = $allowedOrigins ? array_map('trim', explode(',', $allowedOrigins)) : [];

// In development, allow localhost (check via env variable, not app())
if (env('CORS_ALLOW_LOCALHOST', false) || env('APP_ENV') === 'local') {
    $originsArray = array_merge($originsArray, [
        'http://localhost:3000',
        'http://localhost:5173',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:5173',
    ]);
}

/*
 * APLICATIA MOBILA (Capacitor) — obligatoriu si in productie.
 *
 * Continutul aplicatiei e servit de un WebView local, deci cererile catre API
 * poarta unul dintre originile de mai jos: `https://localhost` pe Android
 * (schema implicita in Capacitor 6) si `capacitor://localhost` pe iOS.
 *
 * DE CE NU ERA DE AJUNS TenantClientCors: acela e middleware DE RUTA, iar
 * preflight-ul (OPTIONS) e tratat mai devreme, de HandleCors, care raspundea
 * 204 FARA `Access-Control-Allow-Origin` — pentru ca originea nu era in lista
 * de aici. WebView-ul bloca atunci cererea reala, iar ecranele cadeau pe
 * datele demo fara niciun mesaj de eroare.
 *
 * Preflight apare la orice cerere cu `Authorization` sau cu un corp JSON,
 * adica la aproape tot ce face aplicatia dupa login.
 *
 * Riscul e mic: o pagina de browser obisnuita nu poate avea originea
 * `capacitor://localhost`, iar `https://localhost` inseamna un server pe
 * portul 443 al masinii utilizatorului.
 */
$appOrigins = [
    'https://localhost',
    'http://localhost',
    'capacitor://localhost',
    'ionic://localhost',
];

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // SECURITY: Use specific origins instead of wildcard
    'allowed_origins' => array_merge($originsArray, $appOrigins),

    // Allow patterns for tenant subdomains
    'allowed_origins_patterns' => [
        // Example: Allow all subdomains of your main domain
        // '#^https://.*\.yourdomain\.com$#',
    ],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-API-Key',
        'X-Tenant-Domain',
        'X-Request-ID',
    ],

    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
        'X-Request-ID',
    ],

    'max_age' => 86400,

    /*
     * SECURITY: Only enable credentials if specific origins are set.
     * Deliberat NU tine cont de $appOrigins: aplicatia se autentifica prin
     * antetul Authorization, nu prin cookie-uri, deci n-are nevoie de asta —
     * iar activarea automata ar largi suprafata fara motiv.
     */
    'supports_credentials' => !empty($originsArray),
];
