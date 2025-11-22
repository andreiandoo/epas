<?php
return [
  'paths' => ['api/*', 'sanctum/csrf-cookie'],
  'allowed_methods' => ['*'],
  'allowed_origins' => [
    'http://odeon.local:8082',
    'http://embed.local:8083',
    'http://localhost:8082',
    'http://localhost:8083',
  ],
  'allowed_origins_patterns' => [],
  'allowed_headers' => ['*'],
  'exposed_headers' => [],
  'max_age' => 0,
  'supports_credentials' => false,
];
