<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'youtube' => [
        'api_key' => env('YOUTUBE_API_KEY'),
    ],

    'spotify' => [
        'client_id' => env('SPOTIFY_CLIENT_ID'),
        'client_secret' => env('SPOTIFY_CLIENT_SECRET'),
    ],

    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'phone_number' => env('TWILIO_PHONE_NUMBER'),
    ],

    'openweather' => [
        'api_key' => env('OPENWEATHER_API_KEY'),
    ],

    'facebook' => [
        'app_id' => env('FACEBOOK_APP_ID'),
        'app_secret' => env('FACEBOOK_APP_SECRET'),
    ],

    'google_analytics' => [
        'property_id' => env('GOOGLE_ANALYTICS_PROPERTY_ID'),
    ],

    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
    ],

    'esignature' => [
        'provider' => env('ESIGNATURE_PROVIDER', 'opensign'), // opensign, signrequest, boldsign
        'api_key' => env('ESIGNATURE_API_KEY'),
        'api_url' => env('ESIGNATURE_API_URL', 'https://api.opensignlabs.com/v1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Client Configuration
    |--------------------------------------------------------------------------
    |
    | API URL for tenant client packages. This MUST be set to the core
    | platform URL (e.g., https://core.tixello.com) so that tenant websites
    | can make API calls back to the platform.
    |
    | Falls back to APP_URL if not set.
    |
    */
    'tenant_client' => [
        'api_url' => env('TENANT_CLIENT_API_URL', env('APP_URL', 'http://localhost')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloudflare DNS Management
    |--------------------------------------------------------------------------
    |
    | Configuration for Cloudflare DNS API to manage tenant subdomains.
    | Used for tenants who choose "I don't have a website" option
    | to get a subdomain on your managed base domain.
    |
    */
    'cloudflare' => [
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
        'base_domain' => env('CLOUDFLARE_BASE_DOMAIN', 'ticks.ro'),
    ],

];
