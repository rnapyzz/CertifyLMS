<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Concerns\HasQueuedRetryPolicy;
use App\Enums\NotificationType;
use App\Models\QaReply;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 自分が投稿した質問掲示板のスレッドに回答が届いたことを知らせる通知。
 *
 * `ShouldQueue`: 送信(mail + database 両チャネル)を発火元リクエストから切り離す(T-A-05)。
 */
final class QaReplyReceivedNotification extends Notification implements ShouldQueue
{
    use HasQueuedRetryPolicy, Queueable;

    public function __construct(public readonly QaReply $reply) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->reply->loadMissing(['qaThread', 'user']);
        $threadTitle = $this->reply->qaThread->title;

        /** @var User $notifiable */
        return (new MailMessage)
            ->subject('【Certify LMS】質問に回答が届きました')
            ->greeting("{$notifiable->name} 様")
            ->line("あなたの質問「{$threadTitle}」に、{$this->reply->user?->name} さんから回答が届きました。")
            ->line($this->excerpt())
            ->action('質問を確認する', $this->url())
            ->salutation('Certify LMS 運営チーム');
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        $this->reply->loadMissing(['qaThread', 'user']);

        return [
            'notification_type' => NotificationType::QaReplyReceived->value,
            'title' => "「{$this->reply->qaThread->title}」に回答が届きました",
            'message' => $this->excerpt(),
            'url' => $this->url(),
        ];
    }

    private function excerpt(): string
    {
        return mb_strimwidth(preg_replace('/\s+/', ' ', $this->reply->body) ?? '', 0, 80, '…');
    }

    private function url(): string
    {
        return route('qa-board.show', $this->reply->qa_thread_id);
    }
}
