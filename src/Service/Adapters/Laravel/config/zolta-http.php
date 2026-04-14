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
            'skip_commands' => [
                'package:discover',
                'make:zolta-update-namespace',
            ],
        ],

        'paths' => [
            app_path('Services/*/API/Controllers'),
            app_path('Http/Controllers'),
        ],

        'default_response' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | SQLite WSL Helpers
    |--------------------------------------------------------------------------
    */

    'sqlite' => [
        'wsl_path' => base_path('database/database.sqlite'),
        'windows_path' => env('ZOLTA_SQLITE_WINDOWS_PATH', 'C:\\Users\\Public\\zolta-sqlite\\database.sqlite'),
        'wsl_windows_path' => env('ZOLTA_SQLITE_WSL_WINDOWS_PATH', '/mnt/c/Users/Public/zolta-sqlite/database.sqlite'),
        'browser_executable' => env('ZOLTA_SQLITE_BROWSER', 'C:\\Program Files\\DB Browser for SQLite\\DB Browser for SQLite.exe'),
        'backup_dir' => env('ZOLTA_SQLITE_BACKUP_DIR', base_path('database/backups')),
    ],

];
