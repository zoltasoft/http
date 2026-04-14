<?php

declare(strict_types=1);

namespace Zolta\Http\Authorization\Interfaces;

/**
 * Framework-agnostic user identity contract.
 */
interface UserIdentityInterface
{
    public function getId(): string|int;

    /**
     * @return string[] role names (normalized)
     */
    public function getRoles(): array;

    /**
     * @return array<int,array{id:string|null,name:string,permissions?:array<int,array{id:string|null,name:string}>}>
     */
    public function getRoleEntries(): array;

    /**
     * @return string[]
     */
    public function getPermissions(): array;

    public function can(string $permission): bool;

    /**
     * @param  string[]  $abilities
     */
    public function isAuthorized(array $abilities): bool;

    /**
     * Optional method to retrieve the domain user object.
     * Framework adapters may call this if available to get the actual user model.
     *
     * @return mixed
     */
    public function getDomainUser(): mixed;
}
