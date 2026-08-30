<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Concerns\HasQueuedRetryPolicy;
use App\Enums\MeetingReminderWindow;
use App\Enums\NotificationType;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 予約済み面談の前日 / 開始 1 時間前に、受講生・コーチ双方へ届くリマインダー通知。
 * 文面は「受講生: ○○ / コーチ: ○○」の形で当事者名を両方併記するため、
 * 受信者が受講生かコーチかを判定する分岐は持たない。
 *
 * `ShouldQueue`: 送信(mail + database 両チャネル)を Schedule Command から切り離す(T-A-05)。
 */
final class MeetingReminderNotification extends Notification implements ShouldQueue
{
    use HasQueuedRetryPolicy, Queueable;

    public function __construct(
        public readonly Meeting $meeting,
        public readonly MeetingReminderWindow $window,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->meeting->loadMissing('student', 'coach');

        /** @var User $notifiable */
        return (new MailMessage)
            ->subject("【Certify LMS】{$this->subjectSuffix()}")
            ->greeting("{$notifiable->name} 様")
            ->line($this->headline())
            ->line("日時: {$this->scheduledAt()}")
            ->line("受講生: {$this->meeting->student?->name}")
            ->line("コーチ: {$this->meeting->coach?->name}")
            ->action('面談詳細を確認する', $this->url())
            ->salutation('Certify LMS 運営チーム');
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        $this->meeting->loadMissing('student', 'coach');

        return [
            'notification_type' => NotificationType::MeetingReminder->value,
            'title' => $this->headline(),
            'message' => "日時: {$this->scheduledAt()} / 受講生: {$this->meeting->student?->name} / コーチ: {$this->meeting->coach?->name}",
            'url' => $this->url(),
        ];
    }

    private function headline(): string
    {
        return match ($this->window) {
            MeetingReminderWindow::Eve => '明日の面談のリマインダーです',
            MeetingReminderWindow::OneHourBefore => 'まもなく(1 時間後)面談があります',
        };
    }

    private function subjectSuffix(): string
    {
        return match ($this->window) {
            MeetingReminderWindow::Eve => '明日の面談のお知らせ',
            MeetingReminderWindow::OneHourBefore => '面談は 1 時間後です',
        };
    }

    private function scheduledAt(): string
    {
        return $this->meeting->scheduled_at->format('Y/m/d H:i');
    }

    private function url(): string
    {
        return route('meetings.show', $this->meeting);
    }
}
