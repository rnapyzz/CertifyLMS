<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\Meeting;
use App\Models\MeetingReminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendMeetingRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_eve_window_dispatches_reminders_for_tomorrows_meetings(): void
    {
        Notification::fake();
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDay()->setTime(10, 0),
        ]);

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'eve'])
            ->assertExitCode(0);

        $this->assertSame(1, MeetingReminder::where('window', 'eve')->count());
    }

    public function test_one_hour_before_window_dispatches_reminders(): void
    {
        Notification::fake();
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addMinutes(30),
        ]);

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'one_hour_before'])
            ->assertExitCode(0);

        $this->assertSame(1, MeetingReminder::where('window', 'one_hour_before')->count());
    }

    public function test_invalid_window_option_fails(): void
    {
        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'invalid'])
            ->assertExitCode(1);
    }

    public function test_missing_window_option_fails(): void
    {
        $this->artisan('notifications:send-meeting-reminders')
            ->assertExitCode(1);
    }
}
