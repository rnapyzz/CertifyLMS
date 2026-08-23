<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * QaReplyController の HTTP 統合テスト。
 * 回答投稿は受講生 / 担当コーチのみ(admin・担当外コーチは不可)、編集・削除は投稿者本人(削除は admin も可)。
 */
class QaReplyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_allowed_for_student_and_assigned_coach(): void
    {
        $cert = Certification::factory()->published()->create();
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($cert)->for($author)->create();

        $otherStudent = User::factory()->student()->inProgress()->create();
        $response = $this->actingAs($otherStudent)->post(route('qa-board.replies.store', $thread), [
            'body' => '受講生からの回答',
        ]);
        $response->assertRedirect(route('qa-board.show', $thread));
        $this->assertDatabaseHas('qa_replies', ['qa_thread_id' => $thread->id, 'user_id' => $otherStudent->id]);
    }

    public function test_store_forbidden_for_unassigned_coach_and_admin(): void
    {
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($cert)->create();

        $unassignedCoach = User::factory()->coach()->inProgress()->create();
        $this->actingAs($unassignedCoach)->post(route('qa-board.replies.store', $thread), [
            'body' => '担当外コーチの回答',
        ])->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('qa-board.replies.store', $thread), [
            'body' => '管理者の回答',
        ])->assertForbidden();

        $this->assertDatabaseCount('qa_replies', 0);
    }

    public function test_store_allowed_for_assigned_coach(): void
    {
        $cert = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $admin = User::factory()->admin()->create();
        $cert->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $thread = QaThread::factory()->for($cert)->create();

        $this->actingAs($coach)->post(route('qa-board.replies.store', $thread), [
            'body' => '担当コーチの回答',
        ])->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_replies', ['qa_thread_id' => $thread->id, 'user_id' => $coach->id]);
    }

    public function test_edit_and_update_are_author_only(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($thread, 'qaThread')->for($author)->create();

        $this->actingAs($other)->get(route('qa-board.replies.edit', ['thread' => $thread, 'reply' => $reply]))
            ->assertForbidden();

        $response = $this->actingAs($author)->patch(
            route('qa-board.replies.update', ['thread' => $thread, 'reply' => $reply]),
            ['body' => '更新後の回答'],
        );

        $response->assertRedirect(route('qa-board.show', $thread));
        $this->assertSame('更新後の回答', $reply->fresh()->body);
    }

    public function test_destroy_allowed_for_author_and_admin_but_not_others(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($thread, 'qaThread')->for($author)->create();

        $this->actingAs($other)->delete(route('qa-board.replies.destroy', ['thread' => $thread, 'reply' => $reply]))
            ->assertForbidden();

        $this->actingAs($author)->delete(route('qa-board.replies.destroy', ['thread' => $thread, 'reply' => $reply]))
            ->assertRedirect(route('qa-board.show', $thread));
        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);

        $reply2 = QaReply::factory()->for($thread, 'qaThread')->for($author)->create();
        $this->actingAs($admin)->delete(route('admin.qa-board.replies.destroy', ['thread' => $thread, 'reply' => $reply2]))
            ->assertRedirect(route('admin.qa-board.show', $thread));
        $this->assertDatabaseMissing('qa_replies', ['id' => $reply2->id]);
    }

    public function test_reply_from_other_thread_returns_404(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $threadA = QaThread::factory()->create();
        $threadB = QaThread::factory()->create();
        $reply = QaReply::factory()->for($threadA, 'qaThread')->for($author)->create();

        $this->actingAs($author)->get(route('qa-board.replies.edit', ['thread' => $threadB, 'reply' => $reply]))
            ->assertNotFound();
    }
}
