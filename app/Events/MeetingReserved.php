<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Meeting;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 受講生が面談を予約した際に発火するイベント。
 * `App\Listeners\SendMeetingReservedNotification` が担当コーチへの通知発火を担う。
 *
 * @see \App\Http\Controllers\MeetingController::store()
 */
final class MeetingReserved
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Meeting $meeting) {}
}
