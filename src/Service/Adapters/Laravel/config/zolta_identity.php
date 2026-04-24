<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Identity Class
    |--------------------------------------------------------------------------
    |
    | Fully-qualified Identity class used by the package when resolving the
    | current user identity. If left null the default
    | Zolta\Http\Authorization\Identity will be used.
    |
    */
    'class' => env('ZOLTA_IDENTITY_CLASS', null),

    /*
    |--------------------------------------------------------------------------
    | Permission paths
    |--------------------------------------------------------------------------
    |
    | Optional list of dot-paths used to eager-load and extract permissions
    | from the framework user object. These values are consumed by
    | Identity::config() and AuthorizationMatrix when the identity class
    | declares permission paths.
    |
    */
    'permissions' => [
        // 'roles.*.permissions',
        // 'role.permissions',
        // 'permissions'
    ],

    /*
    |--------------------------------------------------------------------------
    | Schema
    |--------------------------------------------------------------------------
    |
    | Optional metadata/schema for the identity. This is identity-specific
    | and may be used by host apps to describe returned identity payloads.
    |
    */
    'schema' => [],

];
