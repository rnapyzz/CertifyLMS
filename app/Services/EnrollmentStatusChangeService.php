<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\EnrollmentStatusLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Enrollment 状態遷移の監査ログ(`EnrollmentStatusLog`)を INSERT する Service。
 *
 * 呼出側 Action がトランザクション内で recordStatusChange() を呼ぶ前提。本 Service 自体は
 * DB::transaction() を持たない(`backend-services.md` の規約準拠、ステートレス INSERT only)。
 *
 * `final` 不採用: Mockery で recordStatusChange を mock してトランザクション原子性の rollback 検証を
 * Action テストで行う可能性があるため(`UserStatusChangeService` と同じ判断軸)。
 *
 * 受講状態の値そのものが変わる遷移(新規登録の初回記録 / 合格 / 不合格)は必ず本メソッドを
 * 経由するチョークポイントであるため、管理者ダッシュボード集計キャッシュの無効化もここに
 * 一本化する(呼出元 Action ごとに forget() を個別に呼ぶと漏れが起きうるため。T-A-06)。
 * 受講解除(status は変えず Enrollment を SoftDelete するのみ)は本メソッドを経由しないため、
 * `App\UseCases\Enrollment\DestroyAction` 側で別途 `EnrollmentStatsService::forgetAdminCache()` を呼ぶ。
 *
 * @see EnrollmentStatsService::forgetAdminCache() の docblock に `DB::afterCommit()` が必須の理由を記載
 */
final class EnrollmentStatusChangeService
{
    public function __construct(
        private readonly EnrollmentStatsService $stats,
    ) {}

    /**
     * @param Enrollment $enrollment 状態遷移する対象 Enrollment
     * @param ?EnrollmentStatus $fromStatus 遷移前ステータス(初回登録時のみ null、それ以降は必須)
     * @param EnrollmentStatus $toStatus 遷移後ステータス
     * @param ?User $changedBy 操作者(null はシステム自動 = Schedule Command 等)
     * @param ?string $reason 変更理由(任意、UI 表示用)
     */
    public function recordStatusChange(
        Enrollment $enrollment,
        ?EnrollmentStatus $fromStatus,
        EnrollmentStatus $toStatus,
        ?User $changedBy,
        ?string $reason = null,
    ): EnrollmentStatusLog {
        $log = $enrollment->statusLogs()->create([
            'from_status' => $fromStatus?->value,
            'to_status' => $toStatus->value,
            'changed_by_user_id' => $changedBy?->id,
            'changed_reason' => $reason,
            'changed_at' => now(),
        ]);

        DB::afterCommit(fn () => $this->stats->forgetAdminCache());

        return $log;
    }
}
