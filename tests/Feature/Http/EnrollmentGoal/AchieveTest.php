<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchieveTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_mark_achieved(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        $response = $this->actingAs($student)->post(route('enrollment-goals.markAchieved', $goal));

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertNotNull($goal->fresh()->achieved_at);
    }

    public function test_cannot_mark_already_achieved_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->learning()->create();
        $goal = EnrollmentGoal::factory()->achieved()->for($enrollment)->create();

        $response = $this->actingAs($student)->postJson(route('enrollment-goals.markAchieved', $goal));

        $response->assertStatus(409);
    }

    public function test_owner_can_unmark_achieved(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->learning()->create();
        $goal = EnrollmentGoal::factory()->achieved()->for($enrollment)->create();

        $response = $this->actingAs($student)->delete(route('enrollment-goals.unmarkAchieved', $goal));

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertNull($goal->fresh()->achieved_at);
    }

    public function test_cannot_unmark_a_goal_that_is_not_achieved(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        $response = $this->actingAs($student)->deleteJson(route('enrollment-goals.unmarkAchieved', $goal));

        $response->assertStatus(409);
    }

    public function test_other_student_cannot_mark_achieved(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner, 'user')->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        $response = $this->actingAs($other)->post(route('enrollment-goals.markAchieved', $goal));

        $response->assertForbidden();
        $this->assertNull($goal->fresh()->achieved_at);
    }

    public function test_coach_cannot_mark_achieved(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        $response = $this->actingAs($coach)->post(route('enrollment-goals.markAchieved', $goal));

        $response->assertForbidden();
    }
}
