<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ChatMessagePosted;
use App\Listeners\Concerns\HasSafeNotificationDispatch;
use App\Models\ChatMember;
use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use App\Services\NotificationEligibilityService;

/**
 * chat ルームの送信者以外の参加者へ、新着メッセージを通知する Listener。
 * ソフト削除(退会)済メンバーの `user` は関連解決時に null になるため自然に除外される。
 *
 * 受信者ごとに個別 `notify()` する(`Notification::send()` の一括送信だと 1 人の配信失敗で
 * 残りの受信者への送信ごと巻き込まれて止まってしまうため)。
 */
final class SendChatMessageNotification
{
    use HasSafeNotificationDispatch;

    public function __construct(
        private readonly NotificationEligibilityService $eligibility,
    ) {}

    public function handle(ChatMessagePosted $event): void
    {
        $message = $event->message;
        $message->loadMissing('chatRoom.members.user');

        $recipients = $message->chatRoom->members
            ->filter(fn (ChatMember $member) => $member->user_id !== $message->sender_user_id)
            ->map(fn (ChatMember $member) => $member->user)
            ->filter(fn (?User $user) => $user !== null && $this->eligibility->isEligible($user));

        foreach ($recipients as $recipient) {
            $this->safeNotify(fn () => $recipient->notify(new ChatMessageReceivedNotification($message)));
        }
    }
}
