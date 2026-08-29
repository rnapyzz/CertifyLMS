<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 連携済コーチの Google カレンダーに自動登録したイベントの ID を保持する。
 * キャンセル時にこの ID を使って対応するイベントを削除する。連携なしで予約された面談、
 * または Google 側への登録に失敗した面談では null のまま(フォールバック仕様)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('google_event_id', 255)->nullable()->after('meeting_url_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('google_event_id');
        });
    }
};
