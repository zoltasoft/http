<?php

declare(strict_types=1);

namespace Zolta\Http\Identity\Laravel;

use Illuminate\Auth\GenericUser;

final class IdentityPrincipal extends GenericUser
{
    public function __construct(public readonly IntrospectedIdentity $identity)
    {
        parent::__construct([
            'id' => $identity->userId,
            'email' => $identity->email,
            'name' => $identity->username,
            'username' => $identity->username,
        ]);
    }
}
