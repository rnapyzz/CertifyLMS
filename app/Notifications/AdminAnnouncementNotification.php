<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Concerns\HasQueuedRetryPolicy;
use App\Enums\NotificationType;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 管理者お知らせ配信の受信通知。遷移先の業務画面を持たないため、`toArray()` に `url` を含めない
 * (`NotificationController::markAsRead` は `url` 未設定時 `notifications.show` にフォールバックし、
 * そこでお知らせ本文の全文を読める)。
 *
 * `ShouldQueue`: 対象受講生が多い一斉配信でも発火元リクエストをブロックしないよう、
 * 送信(mail + database 両チャネル)をバックグラウンドのキューへ逃がす(T-A-05)。
 */
final class AdminAnnouncementNotification extends Notification implements ShouldQueue
{
    use HasQueuedRetryPolicy, Queueable;

    public function __construct(public readonly Announcement $announcement) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        return (new MailMessage)
            ->subject("【Certify LMS】{$this->announcement->title}")
            ->greeting("{$notifiable->name} 様")
            ->line('運営より、以下のお知らせが届いています。')
            ->line($this->announcement->title)
            ->line($this->announcement->body)
            ->salutation('Certify LMS 運営チーム');
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'notification_type' => NotificationType::AdminAnnouncement->value,
            'title' => $this->announcement->title,
            'message' => $this->excerpt(),
            'body' => $this->announcement->body,
        ];
    }

    private function excerpt(): string
    {
        return mb_strimwidth(preg_replace('/\s+/', ' ', $this->announcement->body) ?? '', 0, 80, '…');
    }
}
