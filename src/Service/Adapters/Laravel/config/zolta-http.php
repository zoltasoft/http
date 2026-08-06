<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Attribute Route Caching
    |--------------------------------------------------------------------------
    |
    | When enabled, controllers and routes are loaded from a cached file
    | generated via: php artisan zolta:routes:cache
    |
    */

    'routes' => [

        'cache' => [
            'enabled' => filter_var($_ENV['ZOLTA_ATTR_ROUTE_CACHE'] ?? false, FILTER_VALIDATE_BOOL),
            'ensure_fresh_on_boot' => env('ZOLTA_ATTR_ROUTE_ENSURE_FRESH_ON_BOOT', false),
            'skip_commands' => [
                'package:discover',
                'make:zolta-update-namespace',
            ],
        ],

        'paths' => [
            app_path('Services/*/API/Controllers'),
            app_path('Http/Controllers'),
        ],

        'documentation' => [
            'enabled' => env('ZOLTA_ROUTE_DOCS_ENABLED', false),
            'output_dir' => env('ZOLTA_ROUTE_DOCS_OUTPUT_DIR', base_path('bootstrap/cache')),
            'output_file' => env('ZOLTA_ROUTE_DOCS_OUTPUT_FILE', 'openapi.json'),
            'manifest_file' => env('ZOLTA_ROUTE_DOCS_MANIFEST_FILE', 'openapi_manifest.php'),
            'title' => env('ZOLTA_ROUTE_DOCS_TITLE', env('APP_NAME', 'Laravel') . ' API'),
            'version' => env('ZOLTA_ROUTE_DOCS_VERSION', env('APP_VERSION', '1.0.0')),
            'description' => env(
                'ZOLTA_ROUTE_DOCS_DESCRIPTION',
                'Auto-generated API documentation from Zolta HTTP route attributes.'
            ),
            'server_url' => env('ZOLTA_ROUTE_DOCS_SERVER_URL', env('APP_URL', 'http://localhost')),
            'server_description' => env('ZOLTA_ROUTE_DOCS_SERVER_DESCRIPTION', 'Application server'),
        ],

        'default_response' => null,
    ],
];
