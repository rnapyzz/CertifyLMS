<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use App\UseCases\Announcement\StoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * StoreAction が配信対象タイプごとに正しい受信者を解決し、Announcement の配信実績
 * (dispatched_count / dispatched_at) を確定させ、各受信者に通知を送ることを検証する。
 * 「利用状態やロールによって配信対象に含まれないユーザーがいる」という共通仕様を、
 * 招待中 / 卒業 / 退会済 / コーチ / 管理者が全ターゲット種別で除外されることで確認する。
 */
class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_students_target_notifies_only_in_progress_students(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $eligible = User::factory()->student()->inProgress()->create();
        $invited = User::factory()->student()->invited()->create();
        $graduated = User::factory()->student()->graduated()->create();
        $withdrawn = User::factory()->student()->withdrawn()->create();
        $coach = User::factory()->coach()->create();

        $announcement = app(StoreAction::class)($admin, [
            'title' => '全体お知らせ',
            'body' => '本文',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ]);

        $this->assertSame(1, $announcement->dispatched_count);
        $this->assertNotNull($announcement->dispatched_at);
        Notification::assertSentTo($eligible, AdminAnnouncementNotification::class);
        Notification::assertNotSentTo($invited, AdminAnnouncementNotification::class);
        Notification::assertNotSentTo($graduated, AdminAnnouncementNotification::class);
        Notification::assertNotSentTo($withdrawn, AdminAnnouncementNotification::class);
        Notification::assertNotSentTo($coach, AdminAnnouncementNotification::class);
    }

    public function test_certification_target_notifies_only_enrolled_eligible_students(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();
        $otherCertification = Certification::factory()->published()->create();

        $enrolledEligible = User::factory()->student()->inProgress()->create();
        Enrollment::factory()->for($enrolledEligible, 'user')->for($certification)->create();

        $enrolledButWithdrawn = User::factory()->student()->withdrawn()->create();
        Enrollment::factory()->for($enrolledButWithdrawn, 'user')->for($certification)->create();

        $notEnrolled = User::factory()->student()->inProgress()->create();
        Enrollment::factory()->for($notEnrolled, 'user')->for($otherCertification)->create();

        $announcement = app(StoreAction::class)($admin, [
            'title' => '資格指定お知らせ',
            'body' => '本文',
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $certification->id,
        ]);

        $this->assertSame(1, $announcement->dispatched_count);
        $this->assertSame($certification->id, $announcement->target_certification_id);
        Notification::assertSentTo($enrolledEligible, AdminAnnouncementNotification::class);
        Notification::assertNotSentTo($enrolledButWithdrawn, AdminAnnouncementNotification::class);
        Notification::assertNotSentTo($notEnrolled, AdminAnnouncementNotification::class);
    }

    public function test_user_target_notifies_only_that_user_when_eligible(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $target = User::factory()->student()->inProgress()->create();
        $bystander = User::factory()->student()->inProgress()->create();

        $announcement = app(StoreAction::class)($admin, [
            'title' => '個別お知らせ',
            'body' => '本文',
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $target->id,
        ]);

        $this->assertSame(1, $announcement->dispatched_count);
        $this->assertSame($target->id, $announcement->target_user_id);
        Notification::assertSentTo($target, AdminAnnouncementNotification::class);
        Notification::assertNotSentTo($bystander, AdminAnnouncementNotification::class);
    }

    public function test_user_target_produces_zero_dispatched_count_when_target_is_not_eligible(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $target = User::factory()->student()->graduated()->create();

        $announcement = app(StoreAction::class)($admin, [
            'title' => '個別お知らせ',
            'body' => '本文',
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $target->id,
        ]);

        $this->assertSame(0, $announcement->dispatched_count);
        Notification::assertNothingSent();
    }
}
