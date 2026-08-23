<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_delete_a_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        $response = $this->actingAs($student)->delete(route('enrollment-goals.destroy', $goal));

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseMissing('enrollment_goals', ['id' => $goal->id]);
    }

    public function test_other_student_cannot_delete(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner, 'user')->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        $response = $this->actingAs($other)->delete(route('enrollment-goals.destroy', $goal));

        $response->assertForbidden();
        $this->assertDatabaseHas('enrollment_goals', ['id' => $goal->id]);
    }

    public function test_coach_cannot_delete(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        $response = $this->actingAs($coach)->delete(route('enrollment-goals.destroy', $goal));

        $response->assertForbidden();
    }

    public function test_goals_are_deleted_when_enrollment_is_force_deleted(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        $enrollment->forceDelete();

        $this->assertDatabaseMissing('enrollment_goals', ['id' => $goal->id]);
    }
}
