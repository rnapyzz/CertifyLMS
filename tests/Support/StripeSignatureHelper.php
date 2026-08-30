<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Stripe Webhook 署名(`Stripe-Signature` ヘッダ)をテストコード側で生成するためのヘルパー。
 *
 * Stripe の署名方式は `t={timestamp},v1={hmac_sha256(secret, "{timestamp}.{payload}")}` という
 * ローカル計算のみで完結する(`\Stripe\WebhookSignature::computeSignature` と同一の実装)ため、
 * 実際に Stripe と通信せずとも正規の署名ヘッダを再現でき、`StripeCheckoutService::constructWebhookEvent()`
 * の実装(モックなし)をそのまま検証できる。
 */
trait StripeSignatureHelper
{
    private function makeStripeSignatureHeader(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return "t={$timestamp},v1={$signature}";
    }
}
