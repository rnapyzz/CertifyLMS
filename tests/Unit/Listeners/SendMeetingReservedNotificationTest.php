<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Enums\UserStatus;
use App\Events\MeetingReserved;
use App\Listeners\SendMeetingReservedNotification;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReservedNotification;
use App\Services\NotificationEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendMeetingReservedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifies_coach(): void
    {
        Notification::fake();

        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        (new SendMeetingReservedNotification(new NotificationEligibilityService))->handle(new MeetingReserved($meeting));

        Notification::assertSentTo($coach, MeetingReservedNotification::class);
    }

    public function test_does_not_notify_ineligible_coach(): void
    {
        Notification::fake();

        $coach = User::factory()->coach()->create(['status' => UserStatus::Graduated->value]);
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        (new SendMeetingReservedNotification(new NotificationEligibilityService))->handle(new MeetingReserved($meeting));

        Notification::assertNothingSent();
    }
}
