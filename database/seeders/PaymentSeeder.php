<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MeetingPackStatus;
use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 開発用 追加面談購入(Stripe 決済)シーダー。
 *
 * **設計思想(決済状態の網羅)**: 「完了 / 保留 / 失敗」の 3 状態を固定 student + デモ受講生に
 * 投入し、面談回数履歴画面(status ごとの表示)と残数集計(完了分のみ加算)を実機確認できるようにする。
 *
 * - 固定 student(残数あり): 完了購入 1 件(残数に反映済み)+ 保留購入 1 件(決済ページ離脱中を想定)
 * - 固定 student-noquota(残数 0): 失敗購入 1 件(Checkout Session が期限切れになった想定)
 *
 * 依存順序: `UserSeeder` → `MeetingPackSeeder` → 本 Seeder。
 */
final class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $fixedStudent = User::query()->where('email', 'student@certify-lms.test')->first();
        $noQuotaStudent = User::query()->where('email', 'student-noquota@certify-lms.test')->first();
        $fivePack = MeetingPack::query()->where('status', MeetingPackStatus::Published->value)->where('meeting_count', 5)->first();
        $onePack = MeetingPack::query()->where('status', MeetingPackStatus::Published->value)->where('meeting_count', 1)->first();

        if ($fixedStudent === null || $fivePack === null) {
            $this->command?->warn('PaymentSeeder: 固定 student または公開中の面談パックが存在しません。先に UserSeeder / MeetingPackSeeder を実行してください。');

            return;
        }

        // 完了購入: 3 日前に決済完了、残数に反映済み
        $completed = Payment::create([
            'user_id' => $fixedStudent->id,
            'meeting_pack_id' => $fivePack->id,
            'stripe_checkout_session_id' => 'cs_test_seed_completed_'.$fixedStudent->id,
            'stripe_payment_intent_id' => 'pi_test_seed_completed_'.$fixedStudent->id,
            'quantity' => $fivePack->meeting_count,
            'amount' => $fivePack->price,
            'status' => PaymentStatus::Succeeded->value,
            'paid_at' => now()->subDays(3),
        ]);

        MeetingQuotaTransaction::factory()
            ->purchased(amount: $completed->quantity, paymentId: $completed->id)
            ->create([
                'user_id' => $fixedStudent->id,
                'occurred_at' => $completed->paid_at,
            ]);

        // 保留購入: Checkout ページを離脱したまま Webhook 未着の状態(残数は未反映)
        Payment::create([
            'user_id' => $fixedStudent->id,
            'meeting_pack_id' => $onePack?->id ?? $fivePack->id,
            'stripe_checkout_session_id' => 'cs_test_seed_pending_'.$fixedStudent->id,
            'quantity' => $onePack->meeting_count ?? $fivePack->meeting_count,
            'amount' => $onePack->price ?? $fivePack->price,
            'status' => PaymentStatus::Pending->value,
        ]);

        if ($noQuotaStudent !== null) {
            // 失敗購入: Checkout Session 期限切れ(24 時間)で Failed に確定、残数は変わらない
            Payment::create([
                'user_id' => $noQuotaStudent->id,
                'meeting_pack_id' => $fivePack->id,
                'stripe_checkout_session_id' => 'cs_test_seed_failed_'.$noQuotaStudent->id,
                'quantity' => $fivePack->meeting_count,
                'amount' => $fivePack->price,
                'status' => PaymentStatus::Failed->value,
            ]);
        }
    }
}
