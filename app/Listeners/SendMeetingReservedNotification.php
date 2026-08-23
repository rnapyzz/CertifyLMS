<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MeetingReserved;
use App\Listeners\Concerns\HasSafeNotificationDispatch;
use App\Notifications\MeetingReservedNotification;
use App\Services\NotificationEligibilityService;

/**
 * 面談予約が入ったことを担当コーチへ通知する Listener。
 */
final class SendMeetingReservedNotification
{
    use HasSafeNotificationDispatch;

    public function __construct(
        private readonly NotificationEligibilityService $eligibility,
    ) {}

    public function handle(MeetingReserved $event): void
    {
        $meeting = $event->meeting;
        $meeting->loadMissing('coach');

        $coach = $meeting->coach;

        if ($coach === null || ! $this->eligibility->isEligible($coach)) {
            return;
        }

        $this->safeNotify(fn () => $coach->notify(new MeetingReservedNotification($meeting)));
    }
}
