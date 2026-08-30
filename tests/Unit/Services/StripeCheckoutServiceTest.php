<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\StripeCheckoutService;
use Stripe\Exception\SignatureVerificationException;
use Tests\Support\StripeSignatureHelper;
use Tests\TestCase;
use UnexpectedValueException;

/**
 * `StripeCheckoutService::constructWebhookEvent()` の署名検証を、実際の Stripe SDK 実装に対して
 * (モックせず)検証する。この処理はローカルの HMAC-SHA256 計算のみで完結し外部通信を行わないため、
 * `Tests\Support\StripeSignatureHelper` で正規/不正な署名ヘッダを自前生成して直接テストできる。
 *
 * @group external-api
 */
class StripeCheckoutServiceTest extends TestCase
{
    use StripeSignatureHelper;

    private const WEBHOOK_SECRET = 'whsec_test_secret_1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.webhook_secret' => self::WEBHOOK_SECRET]);
    }

    public function test_construct_webhook_event_returns_event_for_a_validly_signed_payload(): void
    {
        $payload = json_encode([
            'id' => 'evt_test_123',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_123']],
        ]);
        $signature = $this->makeStripeSignatureHeader($payload, self::WEBHOOK_SECRET);

        $service = new StripeCheckoutService;
        $event = $service->constructWebhookEvent($payload, $signature);

        $this->assertSame('evt_test_123', $event->id);
        $this->assertSame('checkout.session.completed', $event->type);
    }

    public function test_construct_webhook_event_throws_when_signature_does_not_match_secret(): void
    {
        $payload = json_encode(['id' => 'evt_test_123', 'object' => 'event', 'type' => 'checkout.session.completed']);
        $signature = $this->makeStripeSignatureHeader($payload, 'whsec_wrong_secret');

        $service = new StripeCheckoutService;

        $this->expectException(SignatureVerificationException::class);

        $service->constructWebhookEvent($payload, $signature);
    }

    public function test_construct_webhook_event_throws_when_payload_is_tampered_with_after_signing(): void
    {
        $originalPayload = json_encode(['id' => 'evt_test_123', 'object' => 'event', 'type' => 'checkout.session.completed']);
        $signature = $this->makeStripeSignatureHeader($originalPayload, self::WEBHOOK_SECRET);
        $tamperedPayload = json_encode(['id' => 'evt_test_999', 'object' => 'event', 'type' => 'checkout.session.completed']);

        $service = new StripeCheckoutService;

        $this->expectException(SignatureVerificationException::class);

        $service->constructWebhookEvent($tamperedPayload, $signature);
    }

    public function test_construct_webhook_event_throws_when_signature_header_is_missing(): void
    {
        $payload = json_encode(['id' => 'evt_test_123', 'object' => 'event', 'type' => 'checkout.session.completed']);

        $service = new StripeCheckoutService;

        $this->expectException(SignatureVerificationException::class);

        $service->constructWebhookEvent($payload, '');
    }

    public function test_construct_webhook_event_throws_when_timestamp_is_outside_tolerance(): void
    {
        $payload = json_encode(['id' => 'evt_test_123', 'object' => 'event', 'type' => 'checkout.session.completed']);
        // Stripe SDK 既定の許容誤差(5分)を超えた古いタイムスタンプで署名する。
        $signature = $this->makeStripeSignatureHeader($payload, self::WEBHOOK_SECRET, timestamp: time() - 3600);

        $service = new StripeCheckoutService;

        $this->expectException(SignatureVerificationException::class);

        $service->constructWebhookEvent($payload, $signature);
    }

    public function test_construct_webhook_event_throws_unexpected_value_exception_for_invalid_json_payload(): void
    {
        $payload = 'not-valid-json';
        $signature = $this->makeStripeSignatureHeader($payload, self::WEBHOOK_SECRET);

        $service = new StripeCheckoutService;

        $this->expectException(UnexpectedValueException::class);

        $service->constructWebhookEvent($payload, $signature);
    }
}
