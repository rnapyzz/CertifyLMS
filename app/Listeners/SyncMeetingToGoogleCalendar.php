<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MeetingReserved;
use App\Services\GoogleCalendarService;

/**
 * 面談予約が成立した際、担当コーチが Google カレンダー連携済であればその予定を自動登録する(S-A-01)。
 * `GoogleCalendarService::createEvent` は未連携 / API 失敗時に自身で例外を握りつぶし null を返すため
 * (フォールバック境界は Service に一本化)、本 Listener は返ってきた event ID を
 * `Meeting.google_event_id` へ保存するだけでよい。
 */
final class SyncMeetingToGoogleCalendar
{
    public function __construct(
        private readonly GoogleCalendarService $googleCalendar,
    ) {}

    public function handle(MeetingReserved $event): void
    {
        $eventId = $this->googleCalendar->createEvent($event->meeting);

        if ($eventId !== null) {
            $event->meeting->update(['google_event_id' => $eventId]);
        }
    }
}
