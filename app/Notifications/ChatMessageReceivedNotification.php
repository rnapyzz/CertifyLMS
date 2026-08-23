<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 参加している chat ルームに新着メッセージが届いたことを知らせる通知。
 */
final class ChatMessageReceivedNotification extends Notification
{
    public function __construct(public readonly ChatMessage $message) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->message->loadMissing('sender');

        /** @var User $notifiable */
        return (new MailMessage)
            ->subject('【Certify LMS】新着メッセージがあります')
            ->greeting("{$notifiable->name} 様")
            ->line("{$this->message->sender?->name} さんからメッセージが届きました。")
            ->line($this->excerpt())
            ->action('メッセージを確認する', $this->url())
            ->salutation('Certify LMS 運営チーム');
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        $this->message->loadMissing('sender');

        return [
            'notification_type' => NotificationType::ChatMessageReceived->value,
            'title' => "{$this->message->sender?->name} さんからメッセージが届きました",
            'message' => $this->excerpt(),
            'url' => $this->url(),
        ];
    }

    private function excerpt(): string
    {
        return mb_strimwidth(preg_replace('/\s+/', ' ', $this->message->body) ?? '', 0, 80, '…');
    }

    private function url(): string
    {
        return route('chat.show', $this->message->chat_room_id);
    }
}
