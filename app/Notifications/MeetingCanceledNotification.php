<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Concerns\HasQueuedRetryPolicy;
use App\Enums\NotificationType;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 面談がキャンセルされたことを、キャンセルした側ではないもう一方の当事者へ知らせる通知。
 *
 * `ShouldQueue`: 送信(mail + database 両チャネル)を発火元リクエストから切り離す(T-A-05)。
 */
final class MeetingCanceledNotification extends Notification implements ShouldQueue
{
    use HasQueuedRetryPolicy, Queueable;

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
        $this->meeting->loadMissing('canceledBy');
        $scheduledAt = $this->meeting->scheduled_at->format('Y/m/d H:i');
        $canceledByName = $this->meeting->canceledBy?->name ?? '相手';

        /** @var User $notifiable */
        return (new MailMessage)
            ->subject('【Certify LMS】面談がキャンセルされました')
            ->greeting("{$notifiable->name} 様")
            ->line("{$canceledByName} さんが面談(予定日時: {$scheduledAt})をキャンセルしました。")
            ->action('面談詳細を確認する', $this->url())
            ->salutation('Certify LMS 運営チーム');
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        $this->meeting->loadMissing('canceledBy');
        $scheduledAt = $this->meeting->scheduled_at->format('Y/m/d H:i');
        $canceledByName = $this->meeting->canceledBy?->name ?? '相手';

        return [
            'notification_type' => NotificationType::MeetingCanceled->value,
            'title' => "{$canceledByName} さんが面談をキャンセルしました",
            'message' => "予定日時: {$scheduledAt}",
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return route('meetings.show', $this->meeting);
    }
}
