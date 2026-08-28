<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Live Chat microservice
    |--------------------------------------------------------------------------
    |
    | Global defaults for the `live-chat` microservice. Per-marketplace values
    | (working hours, greeting/offline copy, branding, anti-bot thresholds,
    | transcript retention) are stored in the microservice pivot `settings`
    | and read via MarketplaceClient::getMicroserviceConfig('live-chat').
    |
    */

    'microservice_slug' => 'live-chat',

    // Transport. 'polling' works with no extra infra (F1). 'reverb' upgrades to
    // websockets (F3) and still falls back to polling client-side.
    'transport' => env('CHAT_TRANSPORT', 'polling'),

    // Client polling cadence (ms) when the widget is open and transport=polling.
    'poll_interval_ms' => (int) env('CHAT_POLL_INTERVAL_MS', 2500),

    'operator' => [
        // Default simultaneous conversations per operator (override per-operator
        // via chat_operator_statuses.max_concurrent_chats).
        'default_max_concurrent_chats' => (int) env('CHAT_MAX_CONCURRENT', 4),
        // Presence heartbeat cache TTL (seconds).
        'presence_ttl_seconds' => (int) env('CHAT_PRESENCE_TTL', 60),
        // An operator is only auto-marked offline after this many minutes with no
        // heartbeat. Kept generous so brief tab-switches (Livewire's wire:poll
        // pauses on hidden tabs) don't flip a working operator offline. Explicit
        // "Offline"/logout still flips immediately.
        'offline_after_minutes' => (int) env('CHAT_OFFLINE_AFTER_MINUTES', 30),
        // Assignment strategy: 'round_robin' | 'least_busy'.
        'assignment_strategy' => env('CHAT_ASSIGNMENT', 'least_busy'),
        // When false (default), new chats stay in the queue until an operator
        // manually claims ("Preia"). When true, an incoming chat is auto-assigned
        // to the least-busy online operator on open.
        'auto_assign' => (bool) env('CHAT_AUTO_ASSIGN', false),
    ],

    'conversation' => [
        // Auto-close an ACTIVE conversation after this many minutes with no new
        // message from either side (both widget countdown and server backstop).
        'inactivity_timeout_minutes' => (int) env('CHAT_INACTIVITY_MINUTES', 15),
        // Queued/offline conversations wait much longer before being swept, so a
        // visitor isn't dropped just because no operator picked up in 4 minutes.
        'queue_timeout_minutes' => (int) env('CHAT_QUEUE_TIMEOUT_MINUTES', 60),
        // Default transcript retention (days) before purge; per-marketplace
        // override lives in the microservice settings.
        'transcript_retention_days' => (int) env('CHAT_RETENTION_DAYS', 365),
    ],

    'anti_bot' => [
        // Max new conversations per IP within the window.
        'max_conversations_per_ip' => (int) env('CHAT_MAX_CONV_PER_IP', 5),
        'window_minutes' => (int) env('CHAT_ANTIBOT_WINDOW', 10),
        // Max messages per minute per conversation.
        'max_messages_per_minute' => (int) env('CHAT_MAX_MSG_PER_MIN', 20),
        // Reject a pre-chat submitted faster than this (bot signal), in seconds.
        'min_prechat_seconds' => (int) env('CHAT_MIN_PRECHAT_SECONDS', 2),
        // Honeypot field name the widget renders hidden; any value => bot.
        'honeypot_field' => env('CHAT_HONEYPOT_FIELD', 'company_website'),
    ],

    'attachments' => [
        'enabled' => (bool) env('CHAT_ATTACHMENTS', true),
        'max_size_kb' => (int) env('CHAT_ATTACH_MAX_KB', 3072),
        // IMAGES ONLY by security decision (no antivirus on the box). Uploads are
        // re-encoded server-side (payload stripped) + stored on a private disk.
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
    ],

];
