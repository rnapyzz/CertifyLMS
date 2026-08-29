<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 追加面談パックの Stripe 決済記録。
 *
 * quantity / amount は購入時点の MeetingPack.meeting_count / price のスナップショット
 * (後からマスタを変更しても過去の購入を監査できるようにするため)。
 *
 * stripe_checkout_session_id が Webhook からの購入特定キー(UNIQUE)。Checkout Session 作成に
 * 成功した後でのみ本テーブルへ INSERT する(Stripe API 呼出失敗時に孤立レコードを残さないため)。
 *
 * meeting_pack_id は nullOnDelete(MeetingPack が削除されても、スナップショット済の
 * quantity / amount があるため購入記録自体は監査目的で残す。表示側は
 * `$payment->meetingPack?->name ?? '—'` で null を許容する設計、resources/views/meeting-quota/success.blade.php 参照)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('meeting_pack_id')->nullable()->constrained('meeting_packs')->nullOnDelete();
            $table->string('stripe_checkout_session_id', 255)->unique();
            $table->string('stripe_payment_intent_id', 255)->nullable();
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('amount');
            $table->string('status', 20);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
