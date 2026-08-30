<?php

declare(strict_types=1);

namespace App\UseCases\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Exceptions\Enrollment\EnrollmentInvalidTransitionException;
use App\Models\Enrollment;
use App\Services\DefaultEnrollmentService;
use App\Services\EnrollmentStatsService;
use Illuminate\Support\Facades\DB;

/**
 * 受講生による受講解除(SoftDelete) Action。learning 状態の Enrollment のみ削除可。
 * passed / failed は履歴として残すため拒否する。
 *
 * 当該 Enrollment が受講生のデフォルト資格だった場合は、他の learning|passed 残存件数で自動振替 / NULL リセット。
 *
 * status 自体は変えず SoftDelete するのみのため `EnrollmentStatusChangeService::recordStatusChange()`
 * を経由しない。しかし `EnrollmentStatsService::adminKpi()` は `deleted_at IS NULL` で絞り込むため
 * この削除も集計結果を変える。管理者ダッシュボード集計キャッシュの無効化を別途行う(T-A-06)。
 */
final class DestroyAction
{
    public function __construct(
        private readonly DefaultEnrollmentService $defaultEnrollmentService,
        private readonly EnrollmentStatsService $stats,
    ) {}

    /**
     * @throws EnrollmentInvalidTransitionException
     */
    public function __invoke(Enrollment $enrollment): void
    {
        if ($enrollment->status !== EnrollmentStatus::Learning) {
            throw EnrollmentInvalidTransitionException::forDestroy();
        }

        DB::transaction(function () use ($enrollment) {
            $user = $enrollment->user;

            $enrollment->delete();

            $this->defaultEnrollmentService->resolveAfterStatusChange($user, $enrollment);

            DB::afterCommit(fn () => $this->stats->forgetAdminCache());
        });
    }
}
