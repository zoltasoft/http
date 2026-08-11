<?php

declare(strict_types=1);

namespace Zolta\Tests\Unit\Identity;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Zolta\Http\Identity\Laravel\Webhooks\WebhookSignatureVerifier;

final class WebhookSignatureVerifierTest extends TestCase
{
    public function test_it_accepts_a_current_signature_from_any_configured_secret(): void
    {
        $payload = '{"event":"identity.updated"}';
        $timestamp = (string) (new DateTimeImmutable)->getTimestamp();
        $signature = 'v1='.hash_hmac('sha256', $timestamp.'.'.$payload, 'current-secret');

        $verified = (new WebhookSignatureVerifier)->verify(
            $payload,
            $timestamp,
            $signature,
            ['previous-secret', 'current-secret'],
        );

        $this->assertTrue($verified);
    }

    public function test_it_rejects_tampered_or_stale_signatures(): void
    {
        $payload = '{"event":"identity.updated"}';
        $timestamp = (string) ((new DateTimeImmutable)->getTimestamp() - 301);
        $signature = 'v1='.hash_hmac('sha256', $timestamp.'.'.$payload, 'secret');

        $this->assertFalse((new WebhookSignatureVerifier)->verify($payload, $timestamp, $signature, ['secret']));
        $this->assertFalse((new WebhookSignatureVerifier)->verify($payload, (string) ((new DateTimeImmutable)->getTimestamp()), 'v1=invalid', ['secret']));
    }
}
