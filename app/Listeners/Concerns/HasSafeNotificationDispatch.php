<?php

declare(strict_types=1);

namespace App\Listeners\Concerns;

use App\UseCases\Dashboard\HasDashboardSafeFetch;
use Closure;
use Throwable;

/**
 * 通知送信の例外境界ヘルパー。
 *
 * `notify()` の呼び出し自体(各 Notification クラスが `ShouldQueue` のためキューへ積むだけの
 * 高速な処理。実際の mail/database 送信は worker が非同期に処理する。T-A-05)は基本的に失敗しないが、
 * DB 接続エラー等でキュー投入自体が失敗した場合に本体の業務処理("HTTP 500"としてユーザーに見える形)を
 * 巻き込まないよう、本 trait の `safeNotify()` で呼び出し全体を囲み、失敗時は `report()` で記録した
 * 上で握りつぶす。
 *
 * @see HasDashboardSafeFetch 同種の例外境界パターン
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
