<?php

declare(strict_types=1);

namespace App\Concerns;

/**
 * キュー投入される通知 / メールの共通リトライ方針。
 *
 * 一時的な送信失敗(SMTP 到達不可等)に対し、間隔を空けながら段階的に最大 5 回まで自動リトライする。
 * すべて失敗した場合は queue worker が `failed_jobs` テーブルへ記録するため、
 * `sail artisan queue:retry {id}`(または `--all`)で後から再投入できる。
 */
trait HasQueuedRetryPolicy
{
    public int $tries = 5;

    /**
     * @return list<int> 各リトライまでの待機秒数(段階的に間隔を広げる)
     */
    public function backoff(): array
    {
        return [10, 30, 60, 300];
    }
}
