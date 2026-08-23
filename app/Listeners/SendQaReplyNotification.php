<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\QaReplyPosted;
use App\Listeners\Concerns\HasSafeNotificationDispatch;
use App\Notifications\QaReplyReceivedNotification;
use App\Services\NotificationEligibilityService;

/**
 * 質問掲示板のスレッド投稿者へ、回答が届いたことを通知する Listener。
 * 投稿者本人が自分のスレッドに回答した場合(あり得ないが念のため)は通知しない。
 */
final class SendQaReplyNotification
{
    use HasSafeNotificationDispatch;

    public function __construct(
        private readonly NotificationEligibilityService $eligibility,
    ) {}

    public function handle(QaReplyPosted $event): void
    {
        $reply = $event->reply;
        $reply->loadMissing('qaThread.user');

        $author = $reply->qaThread?->user;

        if ($author === null || $author->id === $reply->user_id) {
            return;
        }

        if (! $this->eligibility->isEligible($author)) {
            return;
        }

        $this->safeNotify(fn () => $author->notify(new QaReplyReceivedNotification($reply)));
    }
}
