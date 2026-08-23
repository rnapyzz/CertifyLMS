<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Enums\UserStatus;
use App\Events\QaReplyPosted;
use App\Listeners\SendQaReplyNotification;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaReplyReceivedNotification;
use App\Services\NotificationEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendQaReplyNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifies_thread_author(): void
    {
        Notification::fake();

        $author = User::factory()->student()->inProgress()->create();
        $replier = User::factory()->coach()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($cert)->for($author)->create();
        $reply = QaReply::factory()->for($thread, 'qaThread')->for($replier)->create();

        (new SendQaReplyNotification(new NotificationEligibilityService))->handle(new QaReplyPosted($reply));

        Notification::assertSentTo($author, QaReplyReceivedNotification::class);
    }

    public function test_does_not_notify_when_replier_is_the_author(): void
    {
        Notification::fake();

        $author = User::factory()->student()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($cert)->for($author)->create();
        $reply = QaReply::factory()->for($thread, 'qaThread')->for($author)->create();

        (new SendQaReplyNotification(new NotificationEligibilityService))->handle(new QaReplyPosted($reply));

        Notification::assertNothingSent();
    }

    public function test_does_not_notify_ineligible_author(): void
    {
        Notification::fake();

        $author = User::factory()->student()->create(['status' => UserStatus::Graduated->value]);
        $replier = User::factory()->coach()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($cert)->for($author)->create();
        $reply = QaReply::factory()->for($thread, 'qaThread')->for($replier)->create();

        (new SendQaReplyNotification(new NotificationEligibilityService))->handle(new QaReplyPosted($reply));

        Notification::assertNothingSent();
    }
}
