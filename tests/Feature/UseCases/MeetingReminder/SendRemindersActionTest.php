<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\MeetingReminder;

use App\Enums\MeetingReminderWindow;
use App\Models\Meeting;
use App\Models\MeetingReminder;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use App\UseCases\MeetingReminder\SendRemindersAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * SendRemindersAction が window ごとに正しい対象 Meeting を選び、当事者双方(受講生 + コーチ)へ
 * 通知することを検証する。「利用状態によっては配信しない」「重複配信しない」という共通仕様を、
 * 退会済ユーザーの除外・同一 window での二重実行の抑止としてそれぞれ確認する。
 */
class SendRemindersActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_eve_window_notifies_meetings_scheduled_for_tomorrow_only(): void
    {
        Notification::fake();
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();

        $tomorrow = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDay()->setTime(10, 0),
        ]);
        $today = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addHours(3),
        ]);
        $dayAfterTomorrow = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(2)->setTime(10, 0),
        ]);

        $sent = app(SendRemindersAction::class)(MeetingReminderWindow::Eve);

        $this->assertSame(1, $sent);
        $this->assertDatabaseHas('meeting_reminders', [
            'meeting_id' => $tomorrow->id,
            'window' => MeetingReminderWindow::Eve->value,
        ]);
        $this->assertDatabaseMissing('meeting_reminders', ['meeting_id' => $today->id]);
        $this->assertDatabaseMissing('meeting_reminders', ['meeting_id' => $dayAfterTomorrow->id]);
        Notification::assertSentTo($coach, MeetingReminderNotification::class);
        Notification::assertSentTo($student, MeetingReminderNotification::class);
    }

    public function test_one_hour_before_window_notifies_meetings_starting_within_the_next_hour(): void
    {
        Notification::fake();
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();

        $withinWindow = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addMinutes(45),
        ]);
        $tooFar = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addHours(3),
        ]);

        $sent = app(SendRemindersAction::class)(MeetingReminderWindow::OneHourBefore);

        $this->assertSame(1, $sent);
        $this->assertDatabaseHas('meeting_reminders', [
            'meeting_id' => $withinWindow->id,
            'window' => MeetingReminderWindow::OneHourBefore->value,
        ]);
        $this->assertDatabaseMissing('meeting_reminders', ['meeting_id' => $tooFar->id]);
    }

    public function test_canceled_and_completed_meetings_are_excluded(): void
    {
        Notification::fake();
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();

        Meeting::factory()->canceled()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addMinutes(45),
        ]);
        Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create();

        $sent = app(SendRemindersAction::class)(MeetingReminderWindow::OneHourBefore);

        $this->assertSame(0, $sent);
        Notification::assertNothingSent();
    }

    public function test_ineligible_recipient_is_skipped_but_eligible_counterpart_still_notified(): void
    {
        Notification::fake();
        $coach = User::factory()->coach()->inProgress()->create();
        $withdrawnStudent = User::factory()->student()->withdrawn()->create();

        Meeting::factory()->reserved()->forCoach($coach)->forStudent($withdrawnStudent)->create([
            'scheduled_at' => now()->addMinutes(30),
        ]);

        app(SendRemindersAction::class)(MeetingReminderWindow::OneHourBefore);

        Notification::assertSentTo($coach, MeetingReminderNotification::class);
        Notification::assertNotSentTo($withdrawnStudent, MeetingReminderNotification::class);
    }

    public function test_running_the_same_window_twice_does_not_double_send(): void
    {
        Notification::fake();
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();

        Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addMinutes(30),
        ]);

        $firstRun = app(SendRemindersAction::class)(MeetingReminderWindow::OneHourBefore);
        $secondRun = app(SendRemindersAction::class)(MeetingReminderWindow::OneHourBefore);

        $this->assertSame(1, $firstRun);
        $this->assertSame(0, $secondRun);
        $this->assertSame(1, MeetingReminder::count());
        Notification::assertSentToTimes($coach, MeetingReminderNotification::class, 1);
        Notification::assertSentToTimes($student, MeetingReminderNotification::class, 1);
    }

    public function test_eve_and_one_hour_before_are_independent_windows_for_the_same_meeting(): void
    {
        Notification::fake();
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();

        // 前日リマインダーは既に送信済み。開始 1 時間前の対象にも重なる時刻の Meeting。
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addMinutes(30),
        ]);
        MeetingReminder::create([
            'meeting_id' => $meeting->id,
            'window' => MeetingReminderWindow::Eve->value,
            'sent_at' => now()->subDay(),
        ]);

        $sent = app(SendRemindersAction::class)(MeetingReminderWindow::OneHourBefore);

        $this->assertSame(1, $sent, 'window が異なれば別途送信対象になる');
    }
}
