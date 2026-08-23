<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_edit_form(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        $response = $this->actingAs($student)->get(route('enrollment-goals.edit', $goal));

        $response->assertOk();
        $response->assertViewIs('enrollment-goal.edit');
    }

    public function test_other_student_cannot_view_edit_form(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner, 'user')->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        $this->actingAs($other)->get(route('enrollment-goals.edit', $goal))->assertForbidden();
    }

    public function test_owner_can_update_basic_info(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create(['title' => '旧タイトル']);

        $response = $this->actingAs($student)->patch(route('enrollment-goals.update', $goal), [
            'title' => '新タイトル',
            'description' => '更新後の詳細',
            'target_date' => now()->addWeeks(2)->toDateString(),
        ]);

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertSame('新タイトル', $goal->fresh()->title);
    }

    public function test_update_does_not_change_achieved_state(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->learning()->create();
        $goal = EnrollmentGoal::factory()->achieved()->for($enrollment)->create();
        $achievedAt = $goal->achieved_at;

        $this->actingAs($student)->patch(route('enrollment-goals.update', $goal), [
            'title' => $goal->title,
        ]);

        $this->assertEquals($achievedAt->timestamp, $goal->fresh()->achieved_at->timestamp);
    }

    public function test_other_student_cannot_update(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner, 'user')->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        $response = $this->actingAs($other)->patch(route('enrollment-goals.update', $goal), [
            'title' => '不正な更新',
        ]);

        $response->assertForbidden();
    }
}
