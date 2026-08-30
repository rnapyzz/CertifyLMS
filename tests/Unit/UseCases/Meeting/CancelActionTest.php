<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Events\MeetingCanceled;
use App\Exceptions\Mentoring\MeetingAlreadyStartedException;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\CancelAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CancelActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancels_reserved_future_meeting_and_refunds_quota(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $meeting = Meeting::factory()->reserved()->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        $result = (app(CancelAction::class))($meeting, $student);

        $this->assertSame(MeetingStatus::Canceled, $result->status);
        $this->assertSame($student->id, $result->canceled_by_user_id);
        $this->assertNotNull($result->canceled_at);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'related_meeting_id' => $meeting->id,
            'type' => MeetingQuotaTransactionType::Refunded->value,
            'amount' => 1,
        ]);
    }

    public function test_dispatches_meeting_canceled_event_after_commit(): void
    {
        Event::fake([MeetingCanceled::class]);

        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $meeting = Meeting::factory()->reserved()->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        (app(CancelAction::class))($meeting, $student);

        Event::assertDispatched(MeetingCanceled::class, fn ($e) => $e->meeting->id === $meeting->id);
    }

    public function test_rejects_canceling_an_already_canceled_meeting(): void
    {
        $meeting = Meeting::factory()->canceled()->create();

        $this->expectException(MeetingStatusTransitionException::class);

        (app(CancelAction::class))($meeting, $meeting->student);
    }

    public function test_rejects_canceling_a_meeting_that_already_started(): void
    {
        $meeting = Meeting::factory()->reserved()->create([
            'scheduled_at' => now()->subHour(),
        ]);

        $this->expectException(MeetingAlreadyStartedException::class);

        (app(CancelAction::class))($meeting, $meeting->student);
    }
}
