<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 個人目標の閲覧は独自の Policy を持たず、受講登録詳細画面(`enrollments.show`)自体の
 * EnrollmentPolicy::view にそのまま乗る設計になっている。本人 / 担当コーチ / 管理者は閲覧でき、
 * 他受講生は受講登録詳細画面自体に到達できないため目標も見えない、という認可分岐を検証する。
 */
class VisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_their_own_goals_on_enrollment_show(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->learning()->create();
        EnrollmentGoal::factory()->for($enrollment)->create(['title' => '受講生本人の目標']);

        $response = $this->actingAs($student)->get(route('enrollments.show', $enrollment));

        $response->assertOk();
        $response->assertSee('受講生本人の目標');
    }

    public function test_assigned_coach_can_view_but_not_operate(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create(['title' => '担当コーチが閲覧できる目標']);

        $response = $this->actingAs($coach)->get(route('enrollments.show', $enrollment));

        $response->assertOk();
        $response->assertSee('担当コーチが閲覧できる目標');
        $this->assertFalse($coach->can('update', $goal));
        $this->assertFalse($coach->can('delete', $goal));
        $this->assertFalse($coach->can('markAchieved', $goal));
    }

    public function test_admin_can_view_any_students_goals(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->learning()->create();
        EnrollmentGoal::factory()->for($enrollment)->create(['title' => '管理者が閲覧できる目標']);

        $response = $this->actingAs($admin)->get(route('enrollments.show', $enrollment));

        $response->assertOk();
        $response->assertSee('管理者が閲覧できる目標');
    }

    public function test_other_student_cannot_reach_enrollment_show_at_all(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner, 'user')->learning()->create();
        EnrollmentGoal::factory()->for($enrollment)->create(['title' => '他人には見えない目標']);

        $response = $this->actingAs($other)->get(route('enrollments.show', $enrollment));

        $response->assertForbidden();
    }

    public function test_unassigned_coach_cannot_view(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $unassignedCoach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        EnrollmentGoal::factory()->for($enrollment)->create();

        $response = $this->actingAs($unassignedCoach)->get(route('enrollments.show', $enrollment));

        $response->assertForbidden();
    }
}
