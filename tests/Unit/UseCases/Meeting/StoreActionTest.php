<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Events\MeetingReserved;
use App\Exceptions\MeetingQuota\InsufficientMeetingQuotaException;
use App\Exceptions\Mentoring\MeetingNoAvailableCoachException;
use App\Exceptions\Mentoring\MeetingOutOfAvailabilityException;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\StoreAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    private function makeBookableContext(): array
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/room',
        ]);
        $certification = Certification::factory()->published()->create();
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);
        CoachAvailability::factory()->forCoach($coach)->onDay($scheduledAt->dayOfWeek)->timeRange('09:00:00', '18:00:00')->create();
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();

        return compact('coach', 'certification', 'scheduledAt', 'student', 'enrollment');
    }

    public function test_reserves_meeting_with_the_only_available_coach_and_consumes_quota(): void
    {
        ['coach' => $coach, 'scheduledAt' => $scheduledAt, 'student' => $student, 'enrollment' => $enrollment] = $this->makeBookableContext();

        $meeting = (app(StoreAction::class))($enrollment, $scheduledAt, '相談したいです');

        $this->assertSame(MeetingStatus::Reserved, $meeting->status);
        $this->assertSame($coach->id, $meeting->coach_id);
        $this->assertSame($student->id, $meeting->student_id);
        $this->assertSame($scheduledAt->toDateTimeString(), $meeting->scheduled_at->toDateTimeString());
        $this->assertSame('相談したいです', $meeting->topic);
        $this->assertNotNull($meeting->meeting_quota_transaction_id);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'related_meeting_id' => $meeting->id,
            'type' => MeetingQuotaTransactionType::Consumed->value,
            'amount' => -1,
        ]);
    }

    public function test_dispatches_meeting_reserved_event_after_commit(): void
    {
        Event::fake([MeetingReserved::class]);
        ['scheduledAt' => $scheduledAt, 'enrollment' => $enrollment] = $this->makeBookableContext();

        $meeting = (app(StoreAction::class))($enrollment, $scheduledAt, '相談したいです');

        Event::assertDispatched(MeetingReserved::class, fn ($e) => $e->meeting->id === $meeting->id);
    }

    public function test_throws_when_student_has_no_remaining_quota(): void
    {
        ['scheduledAt' => $scheduledAt, 'enrollment' => $enrollment, 'student' => $student] = $this->makeBookableContext();
        $student->update(['max_meetings' => 0]);

        $this->expectException(InsufficientMeetingQuotaException::class);

        (app(StoreAction::class))($enrollment, $scheduledAt, '相談したいです');
    }

    public function test_throws_out_of_availability_when_no_coach_assigned(): void
    {
        // 担当コーチが 1 人もいない資格: MeetingAvailabilityService::validateSlot が
        // available_coach_count=0 として先に検知するため MeetingOutOfAvailabilityException になる
        // (findAvailableCoaches の candidates->isEmpty() まで到達しない)。
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        $this->expectException(MeetingOutOfAvailabilityException::class);

        (app(StoreAction::class))($enrollment, $scheduledAt, '相談したいです');
    }

    public function test_throws_no_available_coach_on_race_condition_double_booking(): void
    {
        // B-A-01 と同じ再現テクニック: 同コーチ・同時刻に canceled の Meeting を先在させる。
        // canceled は候補抽出(予約済コーチ除外)をすり抜けるため validateSlot / findAvailableCoaches の
        // 事前チェックはどちらも通過するが、(coach_id, scheduled_at) UNIQUE 制約には status を問わず
        // 抵触するため、INSERT 時の UniqueConstraintViolationException 経由で
        // MeetingNoAvailableCoachException に変換されることを検証する。
        ['coach' => $coach, 'scheduledAt' => $scheduledAt, 'enrollment' => $enrollment] = $this->makeBookableContext();
        $otherStudent = User::factory()->student()->create();
        Meeting::factory()->canceled()->forCoach($coach)->forStudent($otherStudent)->create([
            'scheduled_at' => $scheduledAt,
        ]);

        $this->expectException(MeetingNoAvailableCoachException::class);

        (app(StoreAction::class))($enrollment, $scheduledAt, '相談したいです');
    }
}
