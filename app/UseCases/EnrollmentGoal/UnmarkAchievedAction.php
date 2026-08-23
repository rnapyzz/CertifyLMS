<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Exceptions\EnrollmentGoal\EnrollmentGoalInvalidTransitionException;
use App\Models\EnrollmentGoal;
use Illuminate\Support\Facades\DB;

/**
 * 個人目標の達成を取り消す(未達成に戻す)ユースケース。誤って達成マークした場合の補正動線。
 */
final class UnmarkAchievedAction
{
    /**
     * @throws EnrollmentGoalInvalidTransitionException 未達成からの呼出
     */
    public function __invoke(EnrollmentGoal $goal): EnrollmentGoal
    {
        if (! $goal->isAchieved()) {
            throw EnrollmentGoalInvalidTransitionException::forUnmarkAchieved();
        }

        return DB::transaction(function () use ($goal) {
            $goal->update(['achieved_at' => null]);

            return $goal->fresh();
        });
    }
}
