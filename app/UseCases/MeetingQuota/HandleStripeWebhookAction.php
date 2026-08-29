<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Event as StripeEvent;

/**
 * Stripe Webhook イベントを処理する(署名検証は呼出元の Controller が済ませている前提)。
 *
 * 対応するイベントのみ処理し、それ以外(想定外のイベント種別)は無視してログのみ残す
 * (「想定外の通知が届いても処理が破綻しない」)。
 *
 * 二重計上防止: `Payment.status` を Pending → Succeeded に更新する 1 回の UPDATE 文に
 * `WHERE status = 'pending'` を必ず付け、更新件数が 0(既に処理済 / 想定外の状態)であれば
 * それ以降の MeetingQuotaTransaction 生成をスキップする。これにより、同じ Stripe イベントが
 * 再送されても、また万一同時に複数リクエストが届いても、残数の加算は高々 1 回しか起きない
 * (DB の行更新の原子性のみに依存し、追加のロックや別テーブルでの重複排除は不要)。
 *
 * `charge.refunded`: 返金の実行操作自体は本チケットのスコープ外(管理者が Stripe ダッシュボードから
 * 手動操作)だが、その結果は resources/views/meeting-pack/management/show.blade.php が
 * 既に PaymentStatus::Refunded バッジを前提にしているため、受動的に反映する
 * (残数を Purchased と同数だけ減算する MeetingQuotaTransaction を追加する)。
 */
final class HandleStripeWebhookAction
{
    public function __invoke(StripeEvent $event): void
    {
        match ($event->type) {
            'checkout.session.completed' => $this->handleCompleted($event),
            'checkout.session.expired' => $this->handleExpired($event),
            'charge.refunded' => $this->handleRefunded($event),
            default => Log::info('Stripe webhook: unhandled event type', ['type' => $event->type]),
        };
    }

    private function handleCompleted(StripeEvent $event): void
    {
        $session = $event->data->object;
        $sessionId = (string) ($session->id ?? '');

        if ($sessionId === '') {
            Log::warning('Stripe webhook: checkout.session.completed without session id');

            return;
        }

        if (($session->payment_status ?? null) !== 'paid') {
            // Checkout モード=payment では通常 completed は paid の場合のみ発火するが、念のためガードする。
            return;
        }

        $payment = Payment::query()->where('stripe_checkout_session_id', $sessionId)->first();
        if ($payment === null) {
            Log::warning('Stripe webhook: payment not found for session', ['session_id' => $sessionId]);

            return;
        }

        DB::transaction(function () use ($payment, $session) {
            $updated = Payment::query()
                ->where('id', $payment->id)
                ->where('status', PaymentStatus::Pending->value)
                ->update([
                    'status' => PaymentStatus::Succeeded->value,
                    'stripe_payment_intent_id' => $session->payment_intent ?? null,
                    'paid_at' => now(),
                ]);

            if ($updated === 0) {
                // 既に Succeeded(または Failed)に確定済み。イベント再送による二重計上を防ぐため何もしない。
                return;
            }

            MeetingQuotaTransaction::create([
                'user_id' => $payment->user_id,
                'type' => MeetingQuotaTransactionType::Purchased,
                'amount' => $payment->quantity,
                'related_payment_id' => $payment->id,
                'occurred_at' => now(),
            ]);
        });
    }

    private function handleExpired(StripeEvent $event): void
    {
        $session = $event->data->object;
        $sessionId = (string) ($session->id ?? '');

        if ($sessionId === '') {
            return;
        }

        Payment::query()
            ->where('stripe_checkout_session_id', $sessionId)
            ->where('status', PaymentStatus::Pending->value)
            ->update(['status' => PaymentStatus::Failed->value]);
    }

    private function handleRefunded(StripeEvent $event): void
    {
        $charge = $event->data->object;
        $paymentIntentId = (string) ($charge->payment_intent ?? '');

        if ($paymentIntentId === '') {
            return;
        }

        $payment = Payment::query()->where('stripe_payment_intent_id', $paymentIntentId)->first();
        if ($payment === null) {
            return;
        }

        DB::transaction(function () use ($payment) {
            $updated = Payment::query()
                ->where('id', $payment->id)
                ->where('status', PaymentStatus::Succeeded->value)
                ->update(['status' => PaymentStatus::Refunded->value]);

            if ($updated === 0) {
                // Succeeded 以外(Pending / Failed / 既に Refunded)からは遷移しない。二重計上防止と同じ原理。
                return;
            }

            MeetingQuotaTransaction::create([
                'user_id' => $payment->user_id,
                'type' => MeetingQuotaTransactionType::Refunded,
                'amount' => -$payment->quantity,
                'related_payment_id' => $payment->id,
                'occurred_at' => now(),
            ]);
        });
    }
}
