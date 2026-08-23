<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanNotDeletableException;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * プランを物理削除するユースケース。参照整合性を守るため、下書き状態 かつ 受講者(ソフト削除済含む)が
 * 1 人も紐づいていない場合のみ削除可(users.plan_id / user_plan_logs.plan_id は restrictOnDelete)。
 */
final class DestroyAction
{
    /**
     * @throws PlanNotDeletableException 下書き状態以外、または受講者が紐づく場合
     */
    public function __invoke(Plan $plan): void
    {
        if ($plan->status !== PlanStatus::Draft || $plan->users()->withTrashed()->exists()) {
            throw new PlanNotDeletableException;
        }

        DB::transaction(fn () => $plan->delete());
    }
}
