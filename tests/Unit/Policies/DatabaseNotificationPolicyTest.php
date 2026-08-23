<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\DatabaseNotificationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatabaseNotificationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view(): void
    {
        $user = User::factory()->student()->create();
        $notification = $this->makeNotification($user);
        $policy = new DatabaseNotificationPolicy;

        $this->assertTrue($policy->view($user, $notification));
    }

    public function test_other_user_cannot_view(): void
    {
        $owner = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $notification = $this->makeNotification($owner);
        $policy = new DatabaseNotificationPolicy;

        $this->assertFalse($policy->view($other, $notification));
    }

    private function makeNotification(User $notifiable): DatabaseNotification
    {
        return $notifiable->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\QaReplyReceivedNotification',
            'data' => ['title' => 'test', 'message' => 'test', 'notification_type' => 'qa_reply_received', 'url' => '/'],
        ]);
    }
}
