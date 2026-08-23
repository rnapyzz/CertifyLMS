<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    private function assignCoach(Certification $certification, User $coach, User $admin): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
    }

    public function test_assigned_coach_can_add_a_note(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $this->assignCoach($certification, $coach, $admin);
        $enrollment = Enrollment::factory()->for($certification)->create();

        $response = $this->actingAs($coach)->post(route('enrollments.notes.store', $enrollment), [
            'body' => '面談で学習計画を確認した。',
        ]);

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'author_id' => $coach->id,
            'body' => '面談で学習計画を確認した。',
        ]);
    }

    public function test_admin_can_add_a_note(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->create();

        $response = $this->actingAs($admin)->post(route('enrollments.notes.store', $enrollment), [
            'body' => '運営からの観察メモ。',
        ]);

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'author_id' => $admin->id,
        ]);
    }

    public function test_body_is_required(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->create();

        $response = $this->actingAs($admin)->post(route('enrollments.notes.store', $enrollment), []);

        $response->assertSessionHasErrors('body');
        $this->assertDatabaseCount('enrollment_notes', 0);
    }

    public function test_body_max_length_is_enforced(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->create();

        $response = $this->actingAs($admin)->post(route('enrollments.notes.store', $enrollment), [
            'body' => str_repeat('あ', 2001),
        ]);

        $response->assertSessionHasErrors('body');
        $this->assertDatabaseCount('enrollment_notes', 0);
    }

    public function test_unassigned_coach_cannot_add_a_note(): void
    {
        $admin = User::factory()->admin()->create();
        $unassignedCoach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->create();

        $response = $this->actingAs($unassignedCoach)->post(route('enrollments.notes.store', $enrollment), [
            'body' => '担当外からの投稿',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('enrollment_notes', 0);
    }

    public function test_student_cannot_add_a_note(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->create();

        $response = $this->actingAs($student)->post(route('enrollments.notes.store', $enrollment), [
            'body' => '受講生からの投稿',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('enrollment_notes', 0);
    }
}
