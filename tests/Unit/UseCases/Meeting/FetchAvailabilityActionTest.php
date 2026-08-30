<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Meeting;

use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\User;
use App\UseCases\Meeting\FetchAvailabilityAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FetchAvailabilityActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_json_ready_slots_for_the_enrollments_certification(): void
    {
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $coach->id,
            'assigned_at' => now(),
        ]);
        $date = now()->startOfDay()->next(Carbon::MONDAY);
        CoachAvailability::factory()->forCoach($coach)->onDay($date->dayOfWeek)->timeRange('09:00:00', '18:00:00')->create();
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();

        $result = (app(FetchAvailabilityAction::class))($enrollment, $date);

        $this->assertSame($date->toDateString(), $result['date']);
        $this->assertNotEmpty($result['slots']);
        $slot = $result['slots'][0];
        $this->assertArrayHasKey('slot_start', $slot);
        $this->assertArrayHasKey('slot_end', $slot);
        $this->assertArrayHasKey('available_coach_count', $slot);
        $this->assertIsString($slot['slot_start']);
        $this->assertGreaterThanOrEqual(1, $slot['available_coach_count']);
    }

    public function test_returns_empty_slots_when_no_coach_assigned(): void
    {
        $certification = Certification::factory()->published()->create();
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();

        $result = (app(FetchAvailabilityAction::class))($enrollment, now()->addDay());

        $this->assertSame([], $result['slots']);
    }
}
