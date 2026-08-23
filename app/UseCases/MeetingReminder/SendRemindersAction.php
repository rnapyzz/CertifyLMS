<?php

declare(strict_types=1);

namespace App\UseCases\MeetingReminder;

use App\Console\Commands\Mentoring\SendMeetingRemindersCommand;
use App\Enums\MeetingReminderWindow;
use App\Enums\MeetingStatus;
use App\Listeners\Concerns\HasSafeNotificationDispatch;
use App\Models\Meeting;
use App\Models\MeetingReminder;
use App\Notifications\MeetingReminderNotification;
use App\Services\NotificationEligibilityService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/**
 * Schedule Command から呼ばれる面談リマインダー配信ユースケース。
 *
 * 対象は「予約済み(reserved)」かつ「window に応じた時間帯に開始する」かつ
 * 「当該 window のリマインダーが未送信」の Meeting に絞り込む(`whereDoesntHave` で
 * 既送信分をクエリ自体から除外するため、定期実行の頻度やコマンドの重複起動によらず
 * 同じ対象を繰り返し処理しても再送されにくい)。
 *
 * その上で `meeting_reminders(meeting_id, window)` の DB 一意制約を最終防衛線として使う。
 * 複数 worker が同時に同じ Meeting を処理しても、`MeetingReminder::create()` の一意制約違反
 * (`UniqueConstraintViolationException`)を捕捉した側は通知を送らずスキップすることで、
 * 二重配信を防ぐ(`App\UseCases\Meeting\AutoCompleteMeetingAction` の行ロックと同じ「冪等にする」
 * という目的を、状態遷移ではなくログテーブルの一意制約で実現する版)。
 *
 * @see SendMeetingRemindersCommand
 */
final class SendRemindersAction
{
    use HasSafeNotificationDispatch;

    public function __construct(
        private readonly NotificationEligibilityService $eligibility,
    ) {}

    public function __invoke(MeetingReminderWindow $window): int
    {
        $sent = 0;
        [$from, $to] = $this->range($window);

        Meeting::query()
            ->where('status', MeetingStatus::Reserved->value)
            ->whereBetween('scheduled_at', [$from, $to])
            ->whereDoesntHave('reminders', fn ($q) => $q->where('window', $window->value))
            ->with(['student', 'coach'])
            ->orderBy('id')
            ->chunk(100, function ($meetings) use ($window, &$sent): void {
                foreach ($meetings as $meeting) {
                    if ($this->markAsSent($meeting, $window)) {
                        $this->notifyParticipants($meeting, $window);
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    private function markAsSent(Meeting $meeting, MeetingReminderWindow $window): bool
    {
        try {
            MeetingReminder::create([
                'meeting_id' => $meeting->id,
                'window' => $window->value,
                'sent_at' => now(),
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    private function notifyParticipants(Meeting $meeting, MeetingReminderWindow $window): void
    {
        foreach ([$meeting->student, $meeting->coach] as $recipient) {
            if ($recipient === null || ! $this->eligibility->isEligible($recipient)) {
                continue;
            }

            $this->safeNotify(fn () => $recipient->notify(new MeetingReminderNotification($meeting, $window)));
        }
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(MeetingReminderWindow $window): array
    {
        return match ($window) {
            MeetingReminderWindow::Eve => [now()->addDay()->startOfDay(), now()->addDay()->endOfDay()],
            MeetingReminderWindow::OneHourBefore => [now(), now()->addHour()],
        };
    }
}
