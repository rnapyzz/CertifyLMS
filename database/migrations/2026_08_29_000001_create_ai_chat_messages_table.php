<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI 相談 1 会話内の個別メッセージ(受講生の発言 / AI の応答)。
 *
 * user_id は会話所有者を denormalize した列(常に conversation.user_id と同じ)。role=user の行数を
 * 「本日の送信回数」として直接カウントするための列で、これにより日次上限チェックが
 * ai_chat_conversations との JOIN 無しで完結する。
 *
 * content は常に空文字以上を保持する(NULL にしない)。Blade 側が `$message->content === ''` で
 * 「応答待ち / エラーで本文なし」を判定しているため、NULL だと同判定が壊れる。
 *
 * model / input_tokens / output_tokens / response_time_ms は AI 応答の観測メタデータ。
 * response_time_ms と output_tokens は画面のメッセージ末尾にも表示される(既存 Blade 仕様)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('ai_chat_conversations')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20);
            $table->string('status', 20);
            $table->text('content');
            $table->text('error_detail')->nullable();
            $table->string('model', 100)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['user_id', 'role', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
    }
};
