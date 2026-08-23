<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `meetings` テーブルの docblock が元々「(coach_id, scheduled_at) UNIQUE で同コーチ × 同時刻の
 * 二重予約を DB レベルで禁止する」と説明していたにもかかわらず、実際の Schema::create() には
 * この UNIQUE 制約が付いていなかった(初回作成時からの実装漏れ)。
 *
 * MeetingController::store() は既にこの制約の存在を前提に、INSERT の
 * UniqueConstraintViolationException を MeetingNoAvailableCoachException(409)に変換する
 * try/catch を持っているが、制約自体が無いため race condition 時に二重予約が成立し得る状態だった。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->unique(['coach_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropUnique(['coach_id', 'scheduled_at']);
        });
    }
};
