<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Authorization Matrix
    |--------------------------------------------------------------------------
    |
    | Map high-level abilities to concrete permission strings. The Zolta
    | authorization layer checks these when a controller is decorated with
    | #[Route(authorized: ['ability_name'])].
    |
    */

    'abilities' => [
        // 'manage_users' => ['users.read', 'users.create', 'users.update', 'users.delete'],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Permission Resolution
    |--------------------------------------------------------------------------
    |
    | How the authorization layer extracts permissions from the authenticated
    | user model. Use dot-notation with wildcards for nested relations.
    |
    */

    'user' => [
        'class' => null,
        'attributes' => [
            'permissions.*.name',
            'role.permissions.*.name',
            'roles.*.permissions.*.name',
        ],
    ],

];
