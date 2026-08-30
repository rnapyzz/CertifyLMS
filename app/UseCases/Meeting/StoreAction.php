<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Events\MeetingReserved;
use App\Exceptions\MeetingQuota\InsufficientMeetingQuotaException;
use App\Exceptions\Mentoring\MeetingNoAvailableCoachException;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Services\CoachMeetingLoadService;
use App\Services\GoogleCalendarService;
use App\Services\MeetingAvailabilityService;
use App\Services\MeetingQuotaService;
use App\UseCases\MeetingQuota\ConsumeQuotaAction;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 受講生の面談予約ユースケース。残面談回数を確認し、空き枠から過去実績最少のコーチを自動割当して
 * reserved で確定する。同時刻 race condition は (coach_id, scheduled_at) UNIQUE 違反として検知し
 * `MeetingNoAvailableCoachException`(409)へ変換する(B-A-01)。
 *
 * 面談回数の消費・`MeetingReserved` イベント発火は同一トランザクション境界に含める。イベントは
 * `DB::afterCommit()` で登録し、コミットが確定した場合のみ発火させる
 * (`App\UseCases\Chat\StoreMessageAction` の Broadcast と同じ方針)。
 */
final class StoreAction
{
    public function __construct(
        private readonly MeetingAvailabilityService $availabilityService,
        private readonly CoachMeetingLoadService $coachLoadService,
        private readonly MeetingQuotaService $quotaService,
        private readonly ConsumeQuotaAction $consumeAction,
        private readonly GoogleCalendarService $googleCalendar,
    ) {}

    /**
     * @throws InsufficientMeetingQuotaException 残面談回数が 0
     * @throws MeetingNoAvailableCoachException 空きコーチなし(race condition による衝突を含む)
     */
    public function __invoke(Enrollment $enrollment, Carbon $scheduledAt, string $topic): Meeting
    {
        $student = $enrollment->user;

        return DB::transaction(function () use ($enrollment, $student, $scheduledAt, $topic) {
            if ($this->quotaService->remaining($student) < 1) {
                throw new InsufficientMeetingQuotaException;
            }

            $this->availabilityService->validateSlot($enrollment->certification, $scheduledAt);

            $candidates = $this->findAvailableCoaches($enrollment->certification, $scheduledAt);
            if ($candidates->isEmpty()) {
                throw new MeetingNoAvailableCoachException;
            }

            $coach = $this->coachLoadService->leastLoadedCoach($candidates);

            try {
                $meeting = Meeting::create([
                    'enrollment_id' => $enrollment->id,
                    'coach_id' => $coach->id,
                    'student_id' => $student->id,
                    'scheduled_at' => $scheduledAt,
                    'status' => MeetingStatus::Reserved->value,
                    'topic' => $topic,
                    'meeting_url_snapshot' => $coach->meeting_url,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                // 同時刻に他受講生が先行予約した race condition: UNIQUE(coach_id, scheduled_at) で弾かれた
                throw new MeetingNoAvailableCoachException($e);
            }

            $transaction = ($this->consumeAction)($student, $meeting->id);
            $meeting->update(['meeting_quota_transaction_id' => $transaction->id]);

            $meeting = $meeting->fresh();

            DB::afterCommit(function () use ($meeting) {
                event(new MeetingReserved($meeting));
            });

            return $meeting;
        });
    }

    /**
     * 担当コーチ集合のうち、(1) 当該時刻に有効な availability 枠があり、(2) 当該時刻に reserved /
     * completed の Meeting を持たず、(3) Google カレンダー連携済であれば当該時刻に Google 側の
     * 予定も持たない、コーチ集合を返す(S-A-01)。Google との通信に失敗した場合は
     * `GoogleCalendarService::busyIntervals` が空配列を返すため、その場合は従来通り LMS 内の
     * 判定のみでフォールバックする。
     *
     * @return Collection<int, User>
     */
    private function findAvailableCoaches(Certification $certification, Carbon $scheduledAt): Collection
    {
        $time = $scheduledAt->format('H:i:s');

        $candidates = $certification->coaches()
            ->whereHas('coachAvailabilities', function ($q) use ($scheduledAt, $time) {
                $q->where('day_of_week', $scheduledAt->dayOfWeek)
                    ->where('is_active', true)
                    ->where('start_time', '<=', $time)
                    ->where('end_time', '>', $time);
            })
            ->whereDoesntHave('meetingsAsCoach', function ($q) use ($scheduledAt) {
                $q->where('scheduled_at', $scheduledAt)
                    ->whereIn('status', [MeetingStatus::Reserved->value, MeetingStatus::Completed->value]);
            })
            ->get();

        $slotEnd = $scheduledAt->copy()->addHour();

        return $candidates->reject(function (User $coach) use ($scheduledAt, $slotEnd) {
            $busy = $this->googleCalendar->busyIntervals($coach, $scheduledAt, $slotEnd);

            foreach ($busy as $interval) {
                if ($scheduledAt->lessThan($interval['end']) && $slotEnd->greaterThan($interval['start'])) {
                    return true;
                }
            }

            return false;
        })->values();
    }
}
