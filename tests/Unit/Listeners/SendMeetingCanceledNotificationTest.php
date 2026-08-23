<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\MeetingCanceled;
use App\Listeners\SendMeetingCanceledNotification;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingCanceledNotification;
use App\Services\NotificationEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendMeetingCanceledNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifies_coach_when_student_canceled(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->canceled()->forStudent($student)->forCoach($coach)->create([
            'canceled_by_user_id' => $student->id,
        ]);

        (new SendMeetingCanceledNotification(new NotificationEligibilityService))->handle(new MeetingCanceled($meeting));

        Notification::assertSentTo($coach, MeetingCanceledNotification::class);
        Notification::assertNotSentTo($student, MeetingCanceledNotification::class);
    }

    public function test_notifies_student_when_coach_canceled(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->canceled()->forStudent($student)->forCoach($coach)->create([
            'canceled_by_user_id' => $coach->id,
        ]);

        (new SendMeetingCanceledNotification(new NotificationEligibilityService))->handle(new MeetingCanceled($meeting));

        Notification::assertSentTo($student, MeetingCanceledNotification::class);
        Notification::assertNotSentTo($coach, MeetingCanceledNotification::class);
    }
}
