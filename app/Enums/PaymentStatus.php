<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 追加面談パック購入(Payment)の決済状態。
 *
 * - Pending: Checkout Session 作成直後の初期状態(受講生が決済画面にいる、または離脱した状態)
 * - Succeeded: Stripe Webhook `checkout.session.completed`(payment_status=paid)で確定
 * - Failed: Stripe Webhook `checkout.session.expired`(24 時間の既定有効期限切れ等)で確定
 * - Refunded: 管理者が Stripe ダッシュボードから返金操作した結果、Webhook `charge.refunded` で反映
 *   (返金の実行操作自体は本チケットのスコープ外、Stripe 側の操作結果を受動的に反映するのみ)
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '保留',
            self::Succeeded => '完了',
            self::Failed => '失敗',
            self::Refunded => '返金済み',
        };
    }
}
