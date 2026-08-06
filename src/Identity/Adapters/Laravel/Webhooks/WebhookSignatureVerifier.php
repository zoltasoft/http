<?php

declare(strict_types=1);

namespace Zolta\Http\Identity\Laravel\Webhooks;

use DateTimeImmutable;

final readonly class WebhookSignatureVerifier
{
    /** @param list<string> $secrets */
    public function verify(string $payload, string $timestamp, string $signature, array $secrets, int $toleranceSeconds = 300): bool
    {
        if ($payload === '' || ! ctype_digit($timestamp) || ! str_starts_with($signature, 'v1=')) {
            return false;
        }
        $now = (new DateTimeImmutable)->getTimestamp();
        if (abs($now - (int) $timestamp) > $toleranceSeconds) {
            return false;
        }

        $provided = substr($signature, 3);
        foreach ($secrets as $secret) {
            if ($secret !== '' && hash_equals(hash_hmac('sha256', $timestamp . '.' . $payload, $secret), $provided)) {
                return true;
            }
        }

        return false;
    }
}
