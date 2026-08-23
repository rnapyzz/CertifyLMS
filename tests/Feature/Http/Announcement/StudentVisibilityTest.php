<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 受講生側の受信を検証する。お知らせは既存の通知基盤に乗るため、専用画面を持たず
 * 通常の通知一覧 / 未読バッジ / 通知詳細ページで業務通知と並列に確認できることを検証する。
 */
class StudentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipient_sees_announcement_in_notification_list_and_can_read_full_body(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => '重要なお知らせ',
            'body' => "本文 1 行目\n本文 2 行目",
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ])->assertRedirect();

        $indexResponse = $this->actingAs($student)->get(route('notifications.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('重要なお知らせ');

        $student->refresh();
        $notification = $student->notifications()->first();
        $this->assertNotNull($notification);

        $showResponse = $this->actingAs($student)->get(route('notifications.show', $notification));
        $showResponse->assertOk();
        $showResponse->assertSee('重要なお知らせ');
        $showResponse->assertSee('本文 1 行目');
        $showResponse->assertSee('本文 2 行目');
    }

    public function test_non_recipient_student_cannot_open_someone_elses_announcement_notification(): void
    {
        $admin = User::factory()->admin()->create();
        $recipient = User::factory()->student()->inProgress()->create();
        $bystander = User::factory()->student()->inProgress()->create();

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => '重要なお知らせ',
            'body' => '本文',
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $recipient->id,
        ])->assertRedirect();

        $recipient->refresh();
        $notification = $recipient->notifications()->first();
        $this->assertNotNull($notification);

        $this->actingAs($bystander)->get(route('notifications.show', $notification))->assertForbidden();
    }
}
