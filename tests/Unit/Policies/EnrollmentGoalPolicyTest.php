<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use App\Policies\EnrollmentGoalPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * EnrollmentGoalPolicy の判定を検証する。CRUD・達成マーク・達成解除のすべてが
 * 「受講登録の本人(受講生)」専用であることを role × 所有者マトリクスで網羅する。
 */
class EnrollmentGoalPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_allows_only_the_enrollment_owner_student(): void
    {
        $owner = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->for($owner, 'user')->create();
        $policy = new EnrollmentGoalPolicy;

        $this->assertTrue($policy->create($owner, $enrollment));
        $this->assertFalse($policy->create($other, $enrollment));
        $this->assertFalse($policy->create($coach, $enrollment));
        $this->assertFalse($policy->create($admin, $enrollment));
    }

    #[DataProvider('abilityMatrix')]
    public function test_goal_scoped_ability_matches_owner_expectation(string $ability): void
    {
        $owner = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->for($owner, 'user')->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();
        $policy = new EnrollmentGoalPolicy;

        $this->assertTrue($policy->{$ability}($owner, $goal), "本人は {$ability} を実行できるはず");
        $this->assertFalse($policy->{$ability}($other, $goal), "他の受講生は {$ability} を実行できないはず");
        $this->assertFalse($policy->{$ability}($coach, $goal), "コーチは {$ability} を実行できないはず");
        $this->assertFalse($policy->{$ability}($admin, $goal), "管理者は {$ability} を実行できないはず");
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function abilityMatrix(): array
    {
        return [
            'update' => ['update'],
            'delete' => ['delete'],
            'markAchieved' => ['markAchieved'],
            'unmarkAchieved' => ['unmarkAchieved'],
        ];
    }
}
