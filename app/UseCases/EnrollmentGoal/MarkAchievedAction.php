<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Exceptions\EnrollmentGoal\EnrollmentGoalInvalidTransitionException;
use App\Models\EnrollmentGoal;
use Illuminate\Support\Facades\DB;

/**
 * 個人目標を達成済にするユースケース。
 */
final class MarkAchievedAction
{
    /**
     * @throws EnrollmentGoalInvalidTransitionException 既に達成済からの呼出
     */
    public function __invoke(EnrollmentGoal $goal): EnrollmentGoal
    {
        if ($goal->isAchieved()) {
            throw EnrollmentGoalInvalidTransitionException::forMarkAchieved();
        }

        return DB::transaction(function () use ($goal) {
            $goal->update(['achieved_at' => now()]);

            return $goal->fresh();
        });
    }
}
