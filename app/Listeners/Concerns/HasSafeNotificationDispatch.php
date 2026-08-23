<?php

declare(strict_types=1);

namespace App\Listeners\Concerns;

use Closure;
use Throwable;

/**
 * 通知送信 Listener の例外境界ヘルパー。
 *
 * 通知はイベント発火元(chat メッセージ投稿 / QA 回答投稿 / 面談予約・キャンセル)の同期処理内で送信される
 * (メール配信のキュー非同期化は本チケットのスコープ外)。メール送信失敗(SMTP 未設定・到達不可等)が
 * 本体の業務処理("HTTP 500"としてユーザーに見える形)を巻き込まないよう、本 trait の `safeNotify()` で
 * 通知送信全体を囲み、失敗時は `report()` で記録した上で握りつぶす。
 *
 * @see \App\UseCases\Dashboard\HasDashboardSafeFetch 同種の例外境界パターン
 */
trait HasSafeNotificationDispatch
{
    private function safeNotify(Closure $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
