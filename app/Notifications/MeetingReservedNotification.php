<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 担当コーチへ、受講生からの面談予約が入ったことを知らせる通知。
 */
final class MeetingReservedNotification extends Notification
{
    public function __construct(public readonly Meeting $meeting) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->meeting->loadMissing('student');
        $scheduledAt = $this->meeting->scheduled_at->format('Y/m/d H:i');

        /** @var User $notifiable */
        return (new MailMessage)
            ->subject('【Certify LMS】面談予約が入りました')
            ->greeting("{$notifiable->name} 様")
            ->line("{$this->meeting->student?->name} さんから面談予約が入りました。")
            ->line("日時: {$scheduledAt}")
            ->action('面談詳細を確認する', $this->url())
            ->salutation('Certify LMS 運営チーム');
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        $this->meeting->loadMissing('student');
        $scheduledAt = $this->meeting->scheduled_at->format('Y/m/d H:i');

        return [
            'notification_type' => NotificationType::MeetingReserved->value,
            'title' => "{$this->meeting->student?->name} さんから面談予約が入りました",
            'message' => "日時: {$scheduledAt}",
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return route('meetings.show', $this->meeting);
    }
}
