<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\User;
use App\Services\GoogleCalendarService;
use App\Services\MeetingAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * S-A-01: 予約画面の空き枠から、連携済コーチの Google カレンダー予定時刻が除外されることを検証する。
 */
class MeetingAvailabilityServiceGoogleTest extends TestCase
{
    use RefreshDatabase;

    private function attachCoach(Certification $certification, User $coach): void
    {
        $admin = User::factory()->admin()->create();
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
    }

    public function test_slot_with_google_busy_interval_is_excluded(): void
    {
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach);

        $monday = now()->startOfDay()->next(Carbon::MONDAY);
        CoachAvailability::factory()->forCoach($coach)->onDay($monday->dayOfWeek)->timeRange('09:00:00', '12:00:00')->create();

        $busyStart = $monday->copy()->setTime(10, 0);

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldReceive('busyIntervals')->andReturn([
            ['start' => $busyStart, 'end' => $busyStart->copy()->addHour()],
        ]);
        $this->app->instance(GoogleCalendarService::class, $mock);

        $slots = app(MeetingAvailabilityService::class)->slotsForCertification($certification, $monday);

        $busySlot = $slots->first(fn (array $s) => $s['slot_start']->equalTo($busyStart));
        $this->assertNull($busySlot, '10時台は Google 予定があるため空きスロットから除外されるはず');

        $freeSlot = $slots->first(fn (array $s) => $s['slot_start']->equalTo($monday->copy()->setTime(9, 0)));
        $this->assertNotNull($freeSlot);
        $this->assertSame(1, $freeSlot['available_coach_count']);
    }

    public function test_unconnected_coach_availability_is_unaffected(): void
    {
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach);

        $monday = now()->startOfDay()->next(Carbon::MONDAY);
        CoachAvailability::factory()->forCoach($coach)->onDay($monday->dayOfWeek)->timeRange('09:00:00', '10:00:00')->create();

        // 未連携コーチは googleCredential が null のため、実 Service でも Google API 呼出しは発生しない。
        $slots = app(MeetingAvailabilityService::class)->slotsForCertification($certification, $monday);

        $freeSlot = $slots->first(fn (array $s) => $s['slot_start']->equalTo($monday->copy()->setTime(9, 0)));
        $this->assertNotNull($freeSlot);
        $this->assertSame(1, $freeSlot['available_coach_count']);
    }
}
