<?php

declare(strict_types=1);

return [
    'connections' => [
        'live' => [
            'base_url' => env('IDENTITY_API_URL'),
            'project' => env('IDENTITY_PROJECT'),
            'client_id' => env('IDENTITY_CLIENT_ID'),
            'client_secret' => env('IDENTITY_CLIENT_SECRET'),
        ],
        'sandbox' => [
            'base_url' => env('IDENTITY_SANDBOX_API_URL', env('IDENTITY_API_URL')),
            'project' => env('IDENTITY_SANDBOX_PROJECT'),
            'client_id' => env('IDENTITY_SANDBOX_CLIENT_ID'),
            'client_secret' => env('IDENTITY_SANDBOX_CLIENT_SECRET'),
        ],
    ],
    'timeout_seconds' => (int) env('IDENTITY_INTROSPECTION_TIMEOUT_SECONDS', 5),
    'cache_seconds' => (int) env('IDENTITY_INTROSPECTION_CACHE_SECONDS', 30),
    'webhook_secrets' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('IDENTITY_WEBHOOK_SECRETS', '')),
    ))),
    'webhook_tolerance_seconds' => (int) env('IDENTITY_WEBHOOK_TOLERANCE_SECONDS', 300),
];
