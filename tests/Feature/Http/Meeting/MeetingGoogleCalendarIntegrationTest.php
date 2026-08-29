<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Meeting;

use App\Enums\MeetingStatus;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * S-A-01: 予約成立時の Google カレンダー予定自動登録・キャンセル時の連動削除・
 * Google 上で予定を持つコーチの自動割当除外を、`GoogleCalendarService` を Mockery で
 * 差し替えてエンドツーエンドに検証する。
 */
class MeetingGoogleCalendarIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function attachCoach(Certification $certification, User $coach, User $admin): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    public function test_store_skips_coach_with_google_conflict_and_books_the_other_coach(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();
        $busyCoach = User::factory()->coach()->inProgress()->create();
        $freeCoach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $busyCoach, $admin);
        $this->attachCoach($certification, $freeCoach, $admin);

        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);
        CoachAvailability::factory()->forCoach($busyCoach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();
        CoachAvailability::factory()->forCoach($freeCoach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();

        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldReceive('busyIntervals')
            ->andReturnUsing(function (User $coach) use ($busyCoach, $scheduledAt) {
                if ($coach->id === $busyCoach->id) {
                    return [['start' => $scheduledAt, 'end' => $scheduledAt->copy()->addHour()]];
                }

                return [];
            });
        $mock->shouldReceive('createEvent')->andReturn(null);
        $this->app->instance(GoogleCalendarService::class, $mock);

        $response = $this->actingAs($student)->post(route('meetings.store', $enrollment), [
            'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
            'topic' => '相談したい',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('meetings', [
            'coach_id' => $freeCoach->id,
            'status' => MeetingStatus::Reserved->value,
        ]);
        $this->assertDatabaseMissing('meetings', ['coach_id' => $busyCoach->id]);
    }

    public function test_store_returns_409_when_the_only_candidate_coach_has_a_google_conflict(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);

        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();

        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldReceive('busyIntervals')->andReturn([
            ['start' => $scheduledAt, 'end' => $scheduledAt->copy()->addHour()],
        ]);
        $this->app->instance(GoogleCalendarService::class, $mock);

        $response = $this->actingAs($student)->post(route('meetings.store', $enrollment), [
            'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
            'topic' => '相談したい',
        ]);

        // MeetingNoAvailableCoachException(409)は Handler により redirect + flash error に変換される
        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('meetings', 0);
    }

    public function test_reserving_a_meeting_creates_a_google_event_and_stores_its_id(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);

        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldReceive('busyIntervals')->andReturn([]);
        $mock->shouldReceive('createEvent')->once()->andReturn('google-event-123');
        $this->app->instance(GoogleCalendarService::class, $mock);

        $this->actingAs($student)->post(route('meetings.store', $enrollment), [
            'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
            'topic' => '相談したい',
        ]);

        $this->assertDatabaseHas('meetings', [
            'coach_id' => $coach->id,
            'google_event_id' => 'google-event-123',
        ]);
    }

    public function test_canceling_a_meeting_with_a_google_event_deletes_it(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $enrollment = Enrollment::factory()->for($student, 'user')->learning()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->forEnrollment($enrollment)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
            'google_event_id' => 'google-event-456',
        ]);

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldReceive('deleteEvent')
            ->once()
            ->with(Mockery::on(fn (User $c) => $c->id === $coach->id), 'google-event-456');
        $this->app->instance(GoogleCalendarService::class, $mock);

        $response = $this->actingAs($student)->post(route('meetings.cancel', $meeting));

        $response->assertRedirect();
        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'status' => MeetingStatus::Canceled->value,
        ]);
    }

    public function test_canceling_a_meeting_without_a_google_event_does_not_call_delete(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $enrollment = Enrollment::factory()->for($student, 'user')->learning()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->forEnrollment($enrollment)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
            'google_event_id' => null,
        ]);

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldNotReceive('deleteEvent');
        $this->app->instance(GoogleCalendarService::class, $mock);

        $this->actingAs($student)->post(route('meetings.cancel', $meeting))->assertRedirect();
    }
}
