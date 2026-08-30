<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\IndexAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_filter_returns_only_upcoming_reserved_meetings(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->create();
        $future = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        $past = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subDays(3)->startOfHour(),
        ]);

        $result = (app(IndexAction::class))($student, 'upcoming');

        $this->assertTrue($result['meetings']->contains('id', $future->id));
        $this->assertFalse($result['meetings']->contains('id', $past->id));
        $this->assertSame('upcoming', $result['filter']);
    }

    public function test_past_filter_returns_only_past_meetings(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->create();
        $future = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        $past = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subDays(3)->startOfHour(),
        ]);

        $result = (app(IndexAction::class))($student, 'past');

        $this->assertTrue($result['meetings']->contains('id', $past->id));
        $this->assertFalse($result['meetings']->contains('id', $future->id));
    }

    public function test_all_filter_returns_both_past_and_future(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->create();
        $future = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        $past = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subDays(3)->startOfHour(),
        ]);

        $result = (app(IndexAction::class))($student, 'all');

        $this->assertTrue($result['meetings']->contains('id', $past->id));
        $this->assertTrue($result['meetings']->contains('id', $future->id));
    }

    public function test_only_returns_own_meetings(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $other = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        Meeting::factory()->reserved()->forCoach($coach)->forStudent($other)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        $result = (app(IndexAction::class))($student, 'all');

        $this->assertCount(0, $result['meetings']);
    }

    public function test_includes_meetings_remaining_from_quota_service(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 4]);

        $result = (app(IndexAction::class))($student, 'upcoming');

        $this->assertSame(4, $result['meetingsRemaining']);
    }
}
