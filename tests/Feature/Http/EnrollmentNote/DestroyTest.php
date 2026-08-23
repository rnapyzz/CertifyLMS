<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_delete_their_note(): void
    {
        $author = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create(['author_id' => $author->id]);

        $response = $this->actingAs($author)->delete(route('enrollment-notes.destroy', $note));

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseMissing('enrollment_notes', ['id' => $note->id]);
    }

    public function test_admin_can_delete_any_note(): void
    {
        $author = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create(['author_id' => $author->id]);

        $response = $this->actingAs($admin)->delete(route('enrollment-notes.destroy', $note));

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseMissing('enrollment_notes', ['id' => $note->id]);
    }

    public function test_other_coach_cannot_delete(): void
    {
        $author = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create(['author_id' => $author->id]);

        $response = $this->actingAs($otherCoach)->delete(route('enrollment-notes.destroy', $note));

        $response->assertForbidden();
        $this->assertDatabaseHas('enrollment_notes', ['id' => $note->id]);
    }

    public function test_student_cannot_delete(): void
    {
        $author = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create(['author_id' => $author->id]);

        $response = $this->actingAs($student)->delete(route('enrollment-notes.destroy', $note));

        $response->assertForbidden();
        $this->assertDatabaseHas('enrollment_notes', ['id' => $note->id]);
    }

    public function test_notes_are_deleted_when_enrollment_is_force_deleted(): void
    {
        $author = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create(['author_id' => $author->id]);

        $enrollment->forceDelete();

        $this->assertDatabaseMissing('enrollment_notes', ['id' => $note->id]);
    }
}
