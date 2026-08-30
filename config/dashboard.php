<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | 管理者ダッシュボード集計のキャッシュ
    |--------------------------------------------------------------------------
    |
    | 全体 KPI(learning / passed / failed 件数)と資格別修了率は全 Enrollment を走査する
    | 重い集計のため、TTL 秒だけ Cache::remember() で保持する(App\Services\EnrollmentStatsService)。
    | 受講状態の遷移(合格 / 不合格 / 退会 等)が起きると
    | App\Services\EnrollmentStatusChangeService::recordStatusChange() が両キーを forget し、
    | 次回表示時に最新値へ更新される(TTL 経過を待たない即時無効化)。
    |
    */
    'admin_kpi_cache_key' => 'dashboard.admin.kpi',

    'admin_completion_rate_cache_key' => 'dashboard.admin.completion_rate',

    'admin_cache_ttl' => (int) env('DASHBOARD_ADMIN_CACHE_TTL', 300),

];
