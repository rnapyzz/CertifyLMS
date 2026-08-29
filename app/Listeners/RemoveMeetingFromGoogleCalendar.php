<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MeetingCanceled;
use App\Services\GoogleCalendarService;

/**
 * 面談がキャンセルされた際、Google カレンダーに自動登録済のイベントがあれば連動削除する(S-A-01)。
 * `GoogleCalendarService::deleteEvent` はベストエフォートで自身で例外を握りつぶすため、
 * 本 Listener は呼び出すだけでよい(削除自体の成否によってキャンセル処理は左右されない)。
 */
final class RemoveMeetingFromGoogleCalendar
{
    public function __construct(
        private readonly GoogleCalendarService $googleCalendar,
    ) {}

    public function handle(MeetingCanceled $event): void
    {
        $meeting = $event->meeting;

        if ($meeting->google_event_id === null) {
            return;
        }

        $meeting->loadMissing('coach');

        if ($meeting->coach === null) {
            return;
        }

        $this->googleCalendar->deleteEvent($meeting->coach, $meeting->google_event_id);
    }
}
