<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_view_edit_form(): void
    {
        $author = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create(['author_id' => $author->id]);

        $response = $this->actingAs($author)->get(route('enrollment-notes.edit', $note));

        $response->assertOk();
    }

    public function test_other_coach_cannot_view_edit_form(): void
    {
        $author = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create(['author_id' => $author->id]);

        $response = $this->actingAs($otherCoach)->get(route('enrollment-notes.edit', $note));

        $response->assertForbidden();
    }

    public function test_author_can_update_their_note(): void
    {
        $author = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create([
            'author_id' => $author->id,
            'body' => '更新前',
        ]);

        $response = $this->actingAs($author)->patch(route('enrollment-notes.update', $note), [
            'body' => '更新後の内容',
        ]);

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '更新後の内容',
        ]);
    }

    public function test_admin_can_update_any_note(): void
    {
        $author = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create(['author_id' => $author->id]);

        $response = $this->actingAs($admin)->patch(route('enrollment-notes.update', $note), [
            'body' => '管理者による修正',
        ]);

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '管理者による修正',
        ]);
    }

    public function test_other_coach_cannot_update_note(): void
    {
        $author = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create([
            'author_id' => $author->id,
            'body' => '書き換えられないはず',
        ]);

        $response = $this->actingAs($otherCoach)->patch(route('enrollment-notes.update', $note), [
            'body' => '不正な更新',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '書き換えられないはず',
        ]);
    }

    public function test_student_cannot_update_note(): void
    {
        $author = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create(['author_id' => $author->id]);

        $response = $this->actingAs($student)->patch(route('enrollment-notes.update', $note), [
            'body' => '受講生による不正な更新',
        ]);

        $response->assertForbidden();
    }

    public function test_body_is_required_on_update(): void
    {
        $author = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create([
            'author_id' => $author->id,
            'body' => '元の内容',
        ]);

        $response = $this->actingAs($author)->patch(route('enrollment-notes.update', $note), [
            'body' => '',
        ]);

        $response->assertSessionHasErrors('body');
        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '元の内容',
        ]);
    }
}
