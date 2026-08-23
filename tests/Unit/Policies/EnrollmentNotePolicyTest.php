<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use App\Policies\EnrollmentNotePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * EnrollmentNotePolicy の判定を検証する。
 * viewAny/create: 担当コーチ(現在の資格アサイン) と admin のみ。student・未担当コーチは不可。
 * update/delete: 作成者本人のコーチ、または admin。担当が外れていても本人であれば編集可。
 */
class EnrollmentNotePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_any_and_create_require_assigned_coach_or_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $assignedCoach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $certification->coaches()->attach($assignedCoach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $enrollment = Enrollment::factory()->for($certification)->create();
        $policy = new EnrollmentNotePolicy;

        $this->assertTrue($policy->viewAny($admin, $enrollment));
        $this->assertTrue($policy->viewAny($assignedCoach, $enrollment));
        $this->assertFalse($policy->viewAny($otherCoach, $enrollment), '担当外コーチは閲覧不可');
        $this->assertFalse($policy->viewAny($student, $enrollment), '受講生は閲覧不可');

        $this->assertTrue($policy->create($admin, $enrollment));
        $this->assertTrue($policy->create($assignedCoach, $enrollment));
        $this->assertFalse($policy->create($otherCoach, $enrollment), '担当外コーチは作成不可');
        $this->assertFalse($policy->create($student, $enrollment), '受講生は作成不可');
    }

    public function test_update_and_delete_are_limited_to_note_author_or_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $author = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create(['author_id' => $author->id]);
        $policy = new EnrollmentNotePolicy;

        $this->assertTrue($policy->update($author, $note), '作成者本人は更新可能');
        $this->assertFalse($policy->update($otherCoach, $note), '他コーチは更新不可');
        $this->assertTrue($policy->update($admin, $note), 'admin は更新可能');

        $this->assertTrue($policy->delete($author, $note), '作成者本人は削除可能');
        $this->assertFalse($policy->delete($otherCoach, $note), '他コーチは削除不可');
        $this->assertTrue($policy->delete($admin, $note), 'admin は削除可能');
    }

    public function test_update_and_delete_allowed_for_author_even_after_unassignment(): void
    {
        $admin = User::factory()->admin()->create();
        $author = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $certification->coaches()->attach($author->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $enrollment = Enrollment::factory()->for($certification)->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create(['author_id' => $author->id]);
        $certification->coaches()->detach($author->id);
        $policy = new EnrollmentNotePolicy;

        $this->assertTrue($policy->update($author, $note), '担当が外れても本人が書いたメモは編集可能');
        $this->assertTrue($policy->delete($author, $note), '担当が外れても本人が書いたメモは削除可能');
    }
}
