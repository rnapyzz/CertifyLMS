<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 面談リマインダーの配信済みログ。`(meeting_id, window)` の一意制約が、Schedule Command の
 * 重複起動・再実行に対する二重配信防止の最終防衛線になる。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_reminders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->constrained()->cascadeOnDelete();
            $table->string('window', 20);
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['meeting_id', 'window']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_reminders');
    }
};
