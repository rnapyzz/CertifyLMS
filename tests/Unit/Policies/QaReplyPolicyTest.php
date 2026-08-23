<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Policies\QaReplyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * QaReplyPolicy の判定を検証する Unit テスト。
 * create: 受講生 / コーチ(担当かつ公開済資格のみ)、admin は不可。
 * update: 投稿者本人のみ。delete: 投稿者本人 または admin。
 */
class QaReplyPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_student_allowed_on_published_certification(): void
    {
        $student = User::factory()->student()->create();
        $publishedCert = Certification::factory()->published()->create();
        $draftCert = Certification::factory()->draft()->create();
        $publishedThread = QaThread::factory()->for($publishedCert)->create();
        $draftThread = QaThread::factory()->for($draftCert)->create();
        $policy = new QaReplyPolicy;

        $this->assertTrue($policy->create($student, $publishedThread));
        $this->assertFalse($policy->create($student, $draftThread), '公開停止中資格のスレッドには回答不可');
    }

    public function test_create_coach_requires_assignment(): void
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
        $policy = new QaReplyPolicy;

        $this->assertTrue($policy->create($coach, $assignedThread));
        $this->assertFalse($policy->create($coach, $otherThread), '担当外資格のスレッドには回答不可');
    }

    public function test_create_admin_cannot_reply(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();
        $policy = new QaReplyPolicy;

        $this->assertFalse($policy->create($admin, $thread));
    }

    public function test_update_is_author_only(): void
    {
        $author = User::factory()->coach()->create();
        $other = User::factory()->student()->create();
        $reply = QaReply::factory()->for($author)->create();
        $policy = new QaReplyPolicy;

        $this->assertTrue($policy->update($author, $reply));
        $this->assertFalse($policy->update($other, $reply));
    }

    public function test_delete_author_or_admin(): void
    {
        $author = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $reply = QaReply::factory()->for($author)->create();
        $policy = new QaReplyPolicy;

        $this->assertTrue($policy->delete($author, $reply));
        $this->assertTrue($policy->delete($admin, $reply), 'admin はモデレーション削除可能');
        $this->assertFalse($policy->delete($other, $reply));
    }
}
