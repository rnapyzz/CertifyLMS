<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * コーチ本人が任意連携する Google アカウントの OAuth トークンを保持するテーブル。
 * 1 コーチにつき連携は 1 件のみ(user_id UNIQUE、再連携は既存行を上書き)。
 *
 * トークンは平文で保存する(本チケットのスコープ外。本番運用では暗号化を推奨。README 参照)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_credentials', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('token_expires_at');
            $table->string('calendar_id', 255)->default('primary');
            $table->timestamp('connected_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_credentials');
    }
};
