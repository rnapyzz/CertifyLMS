<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\ShowAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_loads_all_relations_needed_by_the_detail_view(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create();

        $result = (new ShowAction)($meeting);

        $this->assertTrue($result->relationLoaded('enrollment'));
        $this->assertTrue($result->enrollment->relationLoaded('certification'));
        $this->assertTrue($result->relationLoaded('coach'));
        $this->assertTrue($result->relationLoaded('student'));
        $this->assertTrue($result->relationLoaded('canceledBy'));
        $this->assertTrue($result->relationLoaded('meetingMemo'));
    }

    public function test_returns_the_same_meeting_instance(): void
    {
        $meeting = Meeting::factory()->reserved()->create();

        $result = (new ShowAction)($meeting);

        $this->assertSame($meeting->id, $result->id);
    }
}
