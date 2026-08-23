<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;
use Illuminate\Support\Facades\DB;

/**
 * 個人目標の基本情報を更新するユースケース。達成状態はここでは更新しない。
 */
final class UpdateAction
{
    /**
     * @param array{title: string, description?: ?string, target_date?: ?string} $validated
     */
    public function __invoke(EnrollmentGoal $goal, array $validated): EnrollmentGoal
    {
        return DB::transaction(function () use ($goal, $validated) {
            $goal->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'target_date' => $validated['target_date'] ?? null,
            ]);

            return $goal->fresh();
        });
    }
}
