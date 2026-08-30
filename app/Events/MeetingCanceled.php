<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Controllers\MeetingController;
use App\Models\Meeting;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 面談が当事者(受講生 or コーチ)によってキャンセルされた際に発火するイベント。
 * `App\Listeners\SendMeetingCanceledNotification` がキャンセルした側ではない、もう一方の当事者への
 * 通知発火を担う(`$meeting->canceled_by_user_id` で判定)。
 *
 * @see MeetingController::cancel()
 */
final class MeetingCanceled
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Meeting $meeting) {}
}
