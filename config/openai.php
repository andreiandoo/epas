<?php

return [
    'api_key' => env('OPENAI_API_KEY', ''),
    'model' => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
    'max_tokens' => env('OPENAI_MAX_TOKENS', 1024),
    'temperature' => env('OPENAI_TEMPERATURE', 0.7),

    'chat' => [
        'enabled' => env('CHAT_WIDGET_ENABLED', false),
        'rate_limit_auth' => env('CHAT_RATE_LIMIT_AUTH', 20), // per minute
        'rate_limit_guest' => env('CHAT_RATE_LIMIT_GUEST', 5), // per minute
        'max_messages_per_conversation' => env('CHAT_MAX_MESSAGES', 50),
        'max_message_length' => 1000,
        'max_tool_calls_per_turn' => 5,
    ],
];
