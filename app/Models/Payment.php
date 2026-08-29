<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 追加面談パックの Stripe 決済記録(追加面談購入 Feature 所有)。
 *
 * quantity / amount は購入時点の MeetingPack.meeting_count / price のスナップショット。
 * status=Completed への遷移(Webhook 経由)は、対応する MeetingQuotaTransaction(type=Purchased)を
 * 1 件だけ生成する(`App\UseCases\MeetingQuota\HandleStripeWebhookAction` 参照)。
 *
 * 関連: User(購入者) / MeetingPack(nullable、削除されても購入記録は残す) /
 * MeetingQuotaTransaction(逆参照、`MeetingQuotaTransaction::relatedPayment()`)
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'meeting_pack_id',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'quantity',
        'amount',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'status' => PaymentStatus::class,
        'quantity' => 'integer',
        'amount' => 'integer',
        'paid_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<MeetingPack, $this>
     */
    public function meetingPack(): BelongsTo
    {
        return $this->belongsTo(MeetingPack::class);
    }
}
