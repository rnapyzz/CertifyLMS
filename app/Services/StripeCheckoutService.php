<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MeetingPack;
use App\Models\User;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Event as StripeEvent;
use Stripe\Exception\ExceptionInterface as StripeExceptionInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Stripe Checkout(決済画面の委譲)と Webhook 署名検証を集約する Service。
 *
 * 「決済画面はカード情報を一切受講生から預からず Stripe に委譲する」という要件のため、Checkout
 * Session を作成して Stripe がホストする決済ページへ redirect するだけで、カード情報は本アプリを
 * 一切経由しない。通貨は円(JPY)固定で、Stripe API 上も JPY はゼロ decimal 通貨のため
 * `unit_amount` に金額をそのまま渡す(`MeetingPack.price` も同じ前提で円単位を保持している)。
 *
 * `final` 不採用: 実際に外部 API 通信を行う Service のため、テストでは `Mockery::mock` で
 * 差し替える(`GoogleCalendarService` と同じ理由)。
 */
class StripeCheckoutService
{
    private const CURRENCY = 'jpy';

    /**
     * @throws StripeExceptionInterface API 呼出エラーに加え、API キー未設定時は
     *                                  `Stripe\Exception\InvalidArgumentException`(`ApiErrorException` の subclass ではない)も投げうる
     */
    public function createCheckoutSession(
        User $user,
        MeetingPack $pack,
        string $successUrl,
        string $cancelUrl,
    ): StripeCheckoutSession {
        return $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'client_reference_id' => $user->id,
            'customer_email' => $user->email,
            'line_items' => [[
                'price_data' => [
                    'currency' => self::CURRENCY,
                    'product_data' => [
                        'name' => $pack->name,
                    ],
                    'unit_amount' => $pack->price,
                ],
                'quantity' => 1,
            ]],
            'metadata' => [
                'user_id' => $user->id,
                'meeting_pack_id' => $pack->id,
            ],
            'success_url' => $successUrl.'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
        ], [
            'idempotency_key' => $this->idempotencyKey($user, $pack),
        ]);
    }

    /**
     * 二重クリックや複数タブからの短時間の連続送信で Stripe 側に複数の Checkout Session
     * (＝複数課金)が作られないよう、同一ユーザー・同一パックへの直近 60 秒以内の作成要求を
     * Stripe の idempotency key でまとめる(同じキーでの再送は Stripe が最初のレスポンスを
     * 再利用して返す)。60 秒より後の再購入は別セッションとして扱われ、正当な買い直しは妨げない。
     */
    private function idempotencyKey(User $user, MeetingPack $pack): string
    {
        $window = intdiv(now()->timestamp, 60);

        return hash('sha256', "checkout:{$user->id}:{$pack->id}:{$window}");
    }

    /**
     * Webhook ペイロードの署名を検証し、`Stripe\Event` に変換する。
     *
     * @throws \UnexpectedValueException|SignatureVerificationException
     */
    public function constructWebhookEvent(string $payload, string $signature): StripeEvent
    {
        return Webhook::constructEvent($payload, $signature, (string) config('services.stripe.webhook_secret'));
    }

    private function client(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret'));
    }
}
