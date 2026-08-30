<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Meeting;

use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\IndexAsCoachAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexAsCoachActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_returns_own_meetings_as_coach(): void
    {
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $student = User::factory()->student()->inProgress()->create();
        $own = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        Meeting::factory()->reserved()->forCoach($otherCoach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(4)->startOfHour(),
        ]);

        $result = (app(IndexAsCoachAction::class))($coach, 'all', null, null);

        $this->assertCount(1, $result['meetings']);
        $this->assertTrue($result['meetings']->contains('id', $own->id));
    }

    public function test_filters_by_student_id(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $wanted = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        Meeting::factory()->reserved()->forCoach($coach)->forStudent($otherStudent)->create([
            'scheduled_at' => now()->addDays(4)->startOfHour(),
        ]);

        $result = (app(IndexAsCoachAction::class))($coach, 'all', $student->id, null);

        $this->assertCount(1, $result['meetings']);
        $this->assertTrue($result['meetings']->contains('id', $wanted->id));
        $this->assertSame($student->id, $result['studentFilter']);
    }

    public function test_filters_by_enrollment_id(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->learning()->create();
        $wanted = Meeting::factory()->reserved()->forCoach($coach)->forEnrollment($enrollment)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(4)->startOfHour(),
        ]);

        $result = (app(IndexAsCoachAction::class))($coach, 'all', null, $enrollment->id);

        $this->assertCount(1, $result['meetings']);
        $this->assertTrue($result['meetings']->contains('id', $wanted->id));
        $this->assertSame($enrollment->id, $result['enrollmentFilter']);
    }

    public function test_upcoming_orders_ascending_by_scheduled_at(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->inProgress()->create();
        $later = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(5)->startOfHour(),
        ]);
        $sooner = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(2)->startOfHour(),
        ]);

        $result = (app(IndexAsCoachAction::class))($coach, 'upcoming', null, null);

        $this->assertSame($sooner->id, $result['meetings']->first()->id);
        $this->assertSame($later->id, $result['meetings']->last()->id);
    }
}
