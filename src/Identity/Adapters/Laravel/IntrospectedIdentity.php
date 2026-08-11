<?php

declare(strict_types=1);

namespace Zolta\Http\Identity\Laravel;

final readonly class IntrospectedIdentity
{
    /** @param list<string> $roles @param list<string> $permissions */
    public function __construct(
        public string $userId,
        public string $email,
        public string $username,
        public bool $emailVerified,
        public string $projectId,
        public string $projectSlug,
        public string $projectMode,
        public string $clientId,
        public ?string $sessionId,
        public array $roles,
        public array $permissions,
        public int $authorizationVersion,
        public bool $isTemporary,
        public ?string $temporaryExpiresAt,
        public ?int $expiresAt,
        public string $connection,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload, string $connection): self
    {
        return new self(
            userId: (string) $payload['sub'],
            email: (string) ($payload['email'] ?? ''),
            username: (string) ($payload['username'] ?? ''),
            emailVerified: (bool) ($payload['email_verified'] ?? false),
            projectId: (string) $payload['project_id'],
            projectSlug: (string) ($payload['project_slug'] ?? ''),
            projectMode: (string) ($payload['project_mode'] ?? 'live'),
            clientId: (string) $payload['client_id'],
            sessionId: isset($payload['session_id']) ? (string) $payload['session_id'] : null,
            roles: array_values(array_map(strval(...), (array) ($payload['roles'] ?? []))),
            permissions: array_values(array_map(strval(...), (array) ($payload['permissions'] ?? []))),
            authorizationVersion: (int) ($payload['authorization_version'] ?? 1),
            isTemporary: (bool) ($payload['is_temporary'] ?? false),
            temporaryExpiresAt: isset($payload['temporary_expires_at']) ? (string) $payload['temporary_expires_at'] : null,
            expiresAt: isset($payload['exp']) ? (int) $payload['exp'] : null,
            connection: $connection,
        );
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
