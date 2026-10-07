<?php
/**
 * Template for the secrets file. Copy it to the server as secrets.php, one level ABOVE the web directory
 * (on Ploi: /home/<user>/viaqui.com/secrets.php), and fill in the values. Never commit the real file.
 */
return [
    'api_key' => 'mpc_...',          // marketplace client API key from Tixello core
    'cache_bust_token' => '',        // shared with core for /api/cache-bust.php
    'brevo_api_key' => '',           // transactional email
    'travelpayouts_token' => '',     // Travelpayouts API token (Profile → API token)
    'travelpayouts_marker' => 0,     // Travelpayouts account marker (a number, shown in the account profile)
];
