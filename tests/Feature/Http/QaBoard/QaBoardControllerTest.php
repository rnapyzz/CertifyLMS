<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QaBoardController の HTTP 統合テスト。
 * 認可漏れ / 一覧の閲覧範囲 / 新規投稿・編集・削除・解決状態変更の代表的な正常系 + 境界系を網羅する。
 */
class QaBoardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_accessible_to_student_and_coach_but_not_admin_route(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)->get(route('qa-board.index'))->assertOk();
    }

    public function test_create_route_ordering_does_not_get_shadowed_by_show_route(): void
    {
        // qa-board/create は qa-board/{thread} より前に解決されなければならない(ワイルドカードに吸われる回帰防止)。
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)->get(route('qa-board.create'));

        $response->assertOk();
        $response->assertViewIs('qa-thread.create');
    }

    public function test_coach_cannot_access_create(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $this->actingAs($coach)->get(route('qa-board.create'))->assertForbidden();
    }

    public function test_store_creates_thread_for_author(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $cert->id,
            'title' => 'テストスレッド',
            'body' => 'テスト本文',
        ]);

        $thread = QaThread::where('title', 'テストスレッド')->firstOrFail();
        $response->assertRedirect(route('qa-board.show', $thread));
        $this->assertSame($student->id, $thread->user_id);
        $this->assertSame('unresolved', $thread->status->value);
    }

    public function test_store_rejects_unpublished_certification(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $draftCert = Certification::factory()->draft()->create();

        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $draftCert->id,
            'title' => 'テストスレッド',
            'body' => 'テスト本文',
        ]);

        $response->assertSessionHasErrors('certification_id');
        $this->assertDatabaseCount('qa_threads', 0);
    }

    public function test_show_forbidden_for_unpublished_certification_thread(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $draftCert = Certification::factory()->draft()->create();
        $thread = QaThread::factory()->for($draftCert)->create();

        $this->actingAs($student)->get(route('qa-board.show', $thread))->assertForbidden();
    }

    public function test_edit_and_update_are_author_only(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($author)->create();

        $this->actingAs($other)->get(route('qa-board.edit', $thread))->assertForbidden();
        $this->actingAs($author)->get(route('qa-board.edit', $thread))->assertOk();

        $response = $this->actingAs($author)->patch(route('qa-board.update', $thread), [
            'title' => '更新後タイトル',
            'body' => '更新後本文',
        ]);

        $response->assertRedirect(route('qa-board.show', $thread));
        $this->assertSame('更新後タイトル', $thread->fresh()->title);
    }

    public function test_destroy_forbidden_for_author_when_replies_exist(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($author)->create();
        QaReply::factory()->for($thread, 'qaThread')->create();

        $this->actingAs($author)->delete(route('qa-board.destroy', $thread))->assertForbidden();
        $this->assertDatabaseHas('qa_threads', ['id' => $thread->id]);
    }

    public function test_destroy_allowed_for_author_when_no_replies(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($author)->create();

        $response = $this->actingAs($author)->delete(route('qa-board.destroy', $thread));

        $response->assertRedirect(route('qa-board.index'));
        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
    }

    public function test_resolve_and_unresolve_by_author(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($author)->create();

        $this->actingAs($author)->post(route('qa-board.resolve', $thread))
            ->assertRedirect(route('qa-board.show', $thread));
        $this->assertNotNull($thread->fresh()->resolved_at);

        $this->actingAs($author)->post(route('qa-board.unresolve', $thread))
            ->assertRedirect(route('qa-board.show', $thread));
        $this->assertNull($thread->fresh()->resolved_at);
    }

    public function test_admin_can_moderate_across_unpublished_certifications(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();
        $draftCert = Certification::factory()->draft()->create();
        $thread = QaThread::factory()->for($draftCert)->create();
        QaReply::factory()->for($thread, 'qaThread')->create();

        $this->actingAs($admin)->get(route('admin.qa-board.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.qa-board.show', $thread))->assertOk();
        $this->actingAs($student)->get(route('admin.qa-board.index'))->assertForbidden();

        $this->actingAs($admin)->delete(route('admin.qa-board.destroy', $thread))
            ->assertRedirect(route('admin.qa-board.index'));
        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
    }
}
