<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use App\Services\StripeCheckoutService;

/**
 * 追加面談パックの Stripe Checkout Session を作成し、購入記録(Payment, status=Pending)を残す。
 *
 * Payment の INSERT は Stripe API 呼出が成功した後に行う(先に作ると、Stripe 側の作成に
 * 失敗した際に孤立した Pending レコードが残ってしまうため)。quantity / amount は
 * MeetingPack の現在値をこの時点でスナップショットし、後からマスタ変更されても
 * 過去の購入内容を監査できるようにする。
 */
final class CreateCheckoutSessionAction
{
    public function __construct(private readonly StripeCheckoutService $stripe) {}

    /**
     * @return string Stripe がホストする Checkout ページの URL
     */
    public function __invoke(User $user, MeetingPack $pack, string $successUrl, string $cancelUrl): string
    {
        $session = $this->stripe->createCheckoutSession($user, $pack, $successUrl, $cancelUrl);

        Payment::create([
            'user_id' => $user->id,
            'meeting_pack_id' => $pack->id,
            'stripe_checkout_session_id' => $session->id,
            'quantity' => $pack->meeting_count,
            'amount' => $pack->price,
            'status' => PaymentStatus::Pending,
        ]);

        return (string) $session->url;
    }
}
