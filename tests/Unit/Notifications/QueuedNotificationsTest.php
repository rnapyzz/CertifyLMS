<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Enums\MeetingReminderWindow;
use App\Models\Announcement;
use App\Models\ChatMessage;
use App\Models\Meeting;
use App\Models\QaReply;
use App\Notifications\AdminAnnouncementNotification;
use App\Notifications\ChatMessageReceivedNotification;
use App\Notifications\MeetingCanceledNotification;
use App\Notifications\MeetingReminderNotification;
use App\Notifications\MeetingReservedNotification;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 通知(チャット / Q&A 返信 / 面談の予約・キャンセル・リマインダー / 管理者お知らせ)が
 * `ShouldQueue` を実装し、発火元リクエストから切り離してキューで送信されることを検証する(T-A-05)。
 * 送信内容自体(toMail / toArray)は各 Listener テストで別途検証済のため、本ファイルは
 * 「非同期化されているか」のみに責務を絞る。
 */
class QueuedNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_announcement_notification_implements_should_queue(): void
    {
        $notification = new AdminAnnouncementNotification(Announcement::factory()->make());

        $this->assertInstanceOf(ShouldQueue::class, $notification);
    }

    public function test_chat_message_received_notification_implements_should_queue(): void
    {
        $notification = new ChatMessageReceivedNotification(ChatMessage::factory()->make());

        $this->assertInstanceOf(ShouldQueue::class, $notification);
    }

    public function test_qa_reply_received_notification_implements_should_queue(): void
    {
        $notification = new QaReplyReceivedNotification(QaReply::factory()->make());

        $this->assertInstanceOf(ShouldQueue::class, $notification);
    }

    public function test_meeting_reserved_notification_implements_should_queue(): void
    {
        $notification = new MeetingReservedNotification(Meeting::factory()->make());

        $this->assertInstanceOf(ShouldQueue::class, $notification);
    }

    public function test_meeting_canceled_notification_implements_should_queue(): void
    {
        $notification = new MeetingCanceledNotification(Meeting::factory()->make());

        $this->assertInstanceOf(ShouldQueue::class, $notification);
    }

    public function test_meeting_reminder_notification_implements_should_queue(): void
    {
        $notification = new MeetingReminderNotification(Meeting::factory()->make(), MeetingReminderWindow::Eve);

        $this->assertInstanceOf(ShouldQueue::class, $notification);
    }

    /**
     * 一時的な送信失敗時に段階的な待機を挟んで自動リトライすることを検証する
     * (`App\Concerns\HasQueuedRetryPolicy`、全 ShouldQueue 通知/メールで共通)。
     */
    public function test_meeting_reserved_notification_retries_with_staged_backoff(): void
    {
        $notification = new MeetingReservedNotification(Meeting::factory()->make());

        $this->assertSame(5, $notification->tries);
        $this->assertSame([10, 30, 60, 300], $notification->backoff());
    }
}
