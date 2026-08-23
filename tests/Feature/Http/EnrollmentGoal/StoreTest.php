<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_add_a_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->learning()->create();

        $response = $this->actingAs($student)->post(route('enrollments.goals.store', $enrollment), [
            'title' => '過去問 5 年分を解き終える',
            'description' => '出題傾向を掴む',
            'target_date' => now()->addMonth()->toDateString(),
        ]);

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseHas('enrollment_goals', [
            'enrollment_id' => $enrollment->id,
            'title' => '過去問 5 年分を解き終える',
        ]);
    }

    public function test_title_is_required(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->learning()->create();

        $response = $this->actingAs($student)->post(route('enrollments.goals.store', $enrollment), [
            'description' => '本文のみ',
        ]);

        $response->assertSessionHasErrors('title');
        $this->assertDatabaseCount('enrollment_goals', 0);
    }

    public function test_other_student_cannot_add_a_goal_to_someone_elses_enrollment(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner, 'user')->learning()->create();

        $response = $this->actingAs($other)->post(route('enrollments.goals.store', $enrollment), [
            'title' => '不正な追加',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('enrollment_goals', 0);
    }

    public function test_coach_cannot_add_a_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->learning()->create();

        $response = $this->actingAs($coach)->post(route('enrollments.goals.store', $enrollment), [
            'title' => 'コーチによる追加',
        ]);

        $response->assertForbidden();
    }
}
