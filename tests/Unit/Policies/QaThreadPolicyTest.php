<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Policies\QaThreadPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * QaThreadPolicy の判定を検証する Unit テスト。
 * view: admin 全可(公開停止中含む) / student・coach は公開済資格のみ、coach はさらに担当資格のみ。
 * update/resolve/unresolve: 投稿者本人のみ。delete: 投稿者本人(未回答の場合のみ) または admin。
 */
class QaThreadPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_admin_can_see_unpublished_certification_thread(): void
    {
        $admin = User::factory()->admin()->create();
        $draftCert = Certification::factory()->draft()->create();
        $thread = QaThread::factory()->for($draftCert)->create();
        $policy = new QaThreadPolicy;

        $this->assertTrue($policy->view($admin, $thread));
    }

    public function test_view_student_only_sees_published_certification_threads(): void
    {
        $student = User::factory()->student()->create();
        $publishedCert = Certification::factory()->published()->create();
        $draftCert = Certification::factory()->draft()->create();
        $publishedThread = QaThread::factory()->for($publishedCert)->create();
        $draftThread = QaThread::factory()->for($draftCert)->create();
        $policy = new QaThreadPolicy;

        $this->assertTrue($policy->view($student, $publishedThread));
        $this->assertFalse($policy->view($student, $draftThread));
    }

    public function test_view_coach_requires_assignment_and_published(): void
    {
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $assignedCert = Certification::factory()->published()->create();
        $otherCert = Certification::factory()->published()->create();
        $assignedCert->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $assignedThread = QaThread::factory()->for($assignedCert)->create();
        $otherThread = QaThread::factory()->for($otherCert)->create();
        $policy = new QaThreadPolicy;

        $this->assertTrue($policy->view($coach, $assignedThread));
        $this->assertFalse($policy->view($coach, $otherThread), '担当外資格のスレッドは view 不可');
    }

    public function test_create_is_student_only(): void
    {
        $policy = new QaThreadPolicy;

        $this->assertTrue($policy->create(User::factory()->student()->make()));
        $this->assertFalse($policy->create(User::factory()->coach()->make()));
        $this->assertFalse($policy->create(User::factory()->admin()->make()));
    }

    public function test_update_is_author_only(): void
    {
        $author = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $thread = QaThread::factory()->for($author)->create();
        $policy = new QaThreadPolicy;

        $this->assertTrue($policy->update($author, $thread));
        $this->assertFalse($policy->update($other, $thread));
    }

    public function test_delete_author_only_allowed_when_no_replies(): void
    {
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->for($author)->create();
        $policy = new QaThreadPolicy;

        $this->assertTrue($policy->delete($author, $thread), '未回答スレッドは投稿者本人が削除可能');

        QaReply::factory()->for($thread, 'qaThread')->create();
        $thread->refresh();

        $this->assertFalse($policy->delete($author, $thread), '回答が付いたスレッドは投稿者でも削除不可');
    }

    public function test_delete_admin_can_always_delete(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();
        QaReply::factory()->for($thread, 'qaThread')->create();
        $thread->refresh();
        $policy = new QaThreadPolicy;

        $this->assertTrue($policy->delete($admin, $thread), 'admin は回答有無に関わらずモデレーション削除可能');
    }

    public function test_resolve_and_unresolve_respect_current_state(): void
    {
        $author = User::factory()->student()->create();
        $unresolved = QaThread::factory()->for($author)->create();
        $resolved = QaThread::factory()->for($author)->resolved()->create();
        $policy = new QaThreadPolicy;

        $this->assertTrue($policy->resolve($author, $unresolved));
        $this->assertFalse($policy->resolve($author, $resolved), '解決済スレッドは resolve 不可');

        $this->assertTrue($policy->unresolve($author, $resolved));
        $this->assertFalse($policy->unresolve($author, $unresolved), '未解決スレッドは unresolve 不可');
    }

    public function test_resolve_admin_cannot_act_on_behalf_of_author(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();
        $policy = new QaThreadPolicy;

        $this->assertFalse($policy->resolve($admin, $thread), '解決マークの代行は admin でも不可');
        $this->assertFalse($policy->update($admin, $thread), '内容編集の代行は admin でも不可');
    }
}
