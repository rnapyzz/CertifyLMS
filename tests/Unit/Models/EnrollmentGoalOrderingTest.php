<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enrollment::goals() の並び順(達成状況が分かりやすい順序)を検証する:
 * 未達成が先頭、未達成内は目標期日の近い順(期日未設定は最後)、達成済内は達成日時の新しい順。
 */
class EnrollmentGoalOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unachieved_goals_come_before_achieved_goals(): void
    {
        $enrollment = Enrollment::factory()->create();
        $achieved = EnrollmentGoal::factory()->achieved()->for($enrollment)->create();
        $unachieved = EnrollmentGoal::factory()->for($enrollment)->create(['target_date' => null]);

        $ids = $enrollment->goals->pluck('id')->all();

        $this->assertSame([$unachieved->id, $achieved->id], $ids);
    }

    public function test_unachieved_goals_are_ordered_by_soonest_target_date_first_nulls_last(): void
    {
        $enrollment = Enrollment::factory()->create();
        $noDate = EnrollmentGoal::factory()->for($enrollment)->create(['target_date' => null]);
        $farDate = EnrollmentGoal::factory()->for($enrollment)->create(['target_date' => now()->addMonth()]);
        $soonDate = EnrollmentGoal::factory()->for($enrollment)->create(['target_date' => now()->addDay()]);

        $ids = $enrollment->goals->pluck('id')->all();

        $this->assertSame([$soonDate->id, $farDate->id, $noDate->id], $ids);
    }

    public function test_achieved_goals_are_ordered_by_most_recently_achieved_first(): void
    {
        $enrollment = Enrollment::factory()->create();
        $olderAchieved = EnrollmentGoal::factory()->for($enrollment)->create(['achieved_at' => now()->subWeek()]);
        $recentAchieved = EnrollmentGoal::factory()->for($enrollment)->create(['achieved_at' => now()->subDay()]);

        $ids = $enrollment->goals->pluck('id')->all();

        $this->assertSame([$recentAchieved->id, $olderAchieved->id], $ids);
    }
}
