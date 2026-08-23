<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * コーチメモの表示は受講登録詳細画面(`enrollments.show`)に埋め込まれる。
 * `@can('viewAny', [EnrollmentNote::class, $enrollment])` でセクションごと出し分けており、
 * 受講生(本人含む)には一切表示されない。担当コーチ・admin には表示され、
 * 編集・削除ボタンは作成者本人 / admin にのみ表示される。
 */
class VisibilityTest extends TestCase
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

    public function test_student_never_sees_coach_notes_section_even_on_own_enrollment(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->learning()->create();
        EnrollmentNote::factory()->for($enrollment)->create(['body' => '受講生には見せないメモ']);

        $response = $this->actingAs($student)->get(route('enrollments.show', $enrollment));

        $response->assertOk();
        $response->assertDontSee('受講生には見せないメモ');
        $response->assertDontSee('コーチメモ');
    }

    public function test_assigned_coach_sees_notes_section_with_own_edit_delete_buttons(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $this->assignCoach($certification, $coach, $admin);
        $this->assignCoach($certification, $otherCoach, $admin);
        $enrollment = Enrollment::factory()->for($certification)->create();
        $ownNote = EnrollmentNote::factory()->for($enrollment)->create([
            'author_id' => $coach->id,
            'body' => '自分が書いたメモ',
        ]);
        $othersNote = EnrollmentNote::factory()->for($enrollment)->create([
            'author_id' => $otherCoach->id,
            'body' => '他コーチが書いたメモ',
        ]);

        $response = $this->actingAs($coach)->get(route('enrollments.show', $enrollment));

        $response->assertOk();
        $response->assertSee('自分が書いたメモ');
        $response->assertSee('他コーチが書いたメモ');
        $response->assertSee(route('enrollment-notes.edit', $ownNote));
        $response->assertDontSee(route('enrollment-notes.edit', $othersNote));
        $response->assertSee(route('enrollment-notes.destroy', $ownNote));
        $response->assertDontSee(route('enrollment-notes.destroy', $othersNote));
    }

    public function test_unassigned_coach_cannot_reach_enrollment_show_at_all(): void
    {
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->create();
        EnrollmentNote::factory()->for($enrollment)->create();

        $response = $this->actingAs($coach)->get(route('enrollments.show', $enrollment));

        $response->assertForbidden();
    }

    public function test_admin_sees_notes_and_can_operate_on_any_note(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create([
            'author_id' => $coach->id,
            'body' => 'コーチが書いたメモ',
        ]);

        $response = $this->actingAs($admin)->get(route('enrollments.show', $enrollment));

        $response->assertOk();
        $response->assertSee('コーチが書いたメモ');
        $response->assertSee(route('enrollment-notes.edit', $note));
        $response->assertSee(route('enrollment-notes.destroy', $note));
    }
}
