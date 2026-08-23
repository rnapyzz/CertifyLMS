<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MeetingCanceled;
use App\Listeners\Concerns\HasSafeNotificationDispatch;
use App\Notifications\MeetingCanceledNotification;
use App\Services\NotificationEligibilityService;

/**
 * 面談キャンセルを、キャンセルした側ではないもう一方の当事者へ通知する Listener。
 */
final class SendMeetingCanceledNotification
{
    use HasSafeNotificationDispatch;

    public function __construct(
        private readonly NotificationEligibilityService $eligibility,
    ) {}

    public function handle(MeetingCanceled $event): void
    {
        $meeting = $event->meeting;
        $meeting->loadMissing('coach', 'student');

        $recipient = $meeting->canceled_by_user_id === $meeting->coach_id
            ? $meeting->student
            : $meeting->coach;

        if ($recipient === null || ! $this->eligibility->isEligible($recipient)) {
            return;
        }

        $this->safeNotify(fn () => $recipient->notify(new MeetingCanceledNotification($meeting)));
    }
}
