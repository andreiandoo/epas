<?php

use App\Logging\Classifiers\AuthClassifier;
use App\Logging\Classifiers\PaymentClassifier;
use App\Logging\Classifiers\EmailClassifier;
use App\Logging\Classifiers\DatabaseClassifier;
use App\Logging\Classifiers\ExternalApiClassifier;
use App\Logging\Classifiers\QueueClassifier;
use App\Logging\Classifiers\PdfClassifier;
use App\Logging\Classifiers\SeatingClassifier;
use App\Logging\Classifiers\SecurityClassifier;
use App\Logging\Classifiers\StorageClassifier;
use App\Logging\Classifiers\IntegrationClassifier;
use App\Logging\Classifiers\CronClassifier;
use App\Logging\Classifiers\FallbackClassifier;

return [
    /*
    |--------------------------------------------------------------------------
    | Capture
    |--------------------------------------------------------------------------
    | Minimum Monolog level that gets persisted to system_errors. Default
    | WARNING (300). Levels below are silently dropped by the handler.
    | Possible values: 100 DEBUG, 200 INFO, 250 NOTICE, 300 WARNING,
    | 400 ERROR, 500 CRITICAL, 550 ALERT, 600 EMERGENCY.
    */
    'capture_level' => env('SYSTEM_ERRORS_CAPTURE_LEVEL', 300),

    /*
    |--------------------------------------------------------------------------
    | Channels piped into system_errors
    |--------------------------------------------------------------------------
    | Names of Monolog channels whose records should be mirrored. Channels
    | not listed here are not captured, even at high severity.
    */
    'channels' => ['daily', 'security', 'marketplace', 'single'],

    /*
    |--------------------------------------------------------------------------
    | Retention (in days), per severity
    |--------------------------------------------------------------------------
    | The prune command deletes rows older than these thresholds. Critical
    | and above kept longest; debug/info kept briefly. Setting any value
    | to null keeps that severity forever.
    */
    'retention' => [
        'critical' => 90, // CRITICAL, ALERT, EMERGENCY
        'error' => 90,
        'warning' => 30,
        'notice' => 14,
        'info' => 7,
        'debug' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Classifier pipeline
    |--------------------------------------------------------------------------
    | Each entry implements ClassifierContract. They run in order; the first
    | to return non-null wins. FallbackClassifier always returns 'unknown'.
    */
    'classifiers' => [
        AuthClassifier::class,
        PaymentClassifier::class,
        EmailClassifier::class,
        DatabaseClassifier::class,
        ExternalApiClassifier::class,
        QueueClassifier::class,
        PdfClassifier::class,
        SeatingClassifier::class,
        SecurityClassifier::class,
        StorageClassifier::class,
        IntegrationClassifier::class,
        CronClassifier::class,
        FallbackClassifier::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | UI polling intervals (seconds)
    |--------------------------------------------------------------------------
    */
    'polling' => [
        'table' => env('SYSTEM_ERRORS_POLL_TABLE', 15),
        'stats' => env('SYSTEM_ERRORS_POLL_STATS', 15),
        'chart' => env('SYSTEM_ERRORS_POLL_CHART', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Truncation limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'message' => 8000,
        'stack_trace' => 65000,
        'context_keys' => 200,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive context keys
    |--------------------------------------------------------------------------
    | Any context key whose name matches (case-insensitive) is replaced with
    | "[REDACTED]" before persisting. Prevents accidentally storing secrets.
    */
    'redact_keys' => [
        'password',
        'password_confirmation',
        'token',
        'api_key',
        'authorization',
        'secret',
        'private_key',
        'webhook_secret',
        'card_number',
        'cvv',
    ],

    /*
    |--------------------------------------------------------------------------
    | Backfill
    |--------------------------------------------------------------------------
    */
    'backfill' => [
        'default_days' => 30,
        'batch_size' => 500,
    ],
];
