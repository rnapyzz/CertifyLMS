<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarkAsReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_as_read_and_redirects_to_linked_url(): void
    {
        $user = User::factory()->student()->create();
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\QaReplyReceivedNotification',
            'data' => ['title' => 'x', 'message' => 'x', 'notification_type' => 'qa_reply_received', 'url' => '/qa-board/some-thread'],
        ]);
        $this->assertNull($notification->read_at);

        $response = $this->actingAs($user)->post(route('notifications.markAsRead', $notification));

        $response->assertRedirect('/qa-board/some-thread');
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_falls_back_to_show_when_no_url(): void
    {
        $user = User::factory()->student()->create();
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\QaReplyReceivedNotification',
            'data' => ['title' => 'x', 'message' => 'x', 'notification_type' => 'admin_announcement'],
        ]);

        $response = $this->actingAs($user)->post(route('notifications.markAsRead', $notification));

        $response->assertRedirect(route('notifications.show', $notification));
    }

    public function test_other_user_cannot_mark_as_read(): void
    {
        $owner = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $notification = $owner->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\QaReplyReceivedNotification',
            'data' => ['title' => 'x', 'message' => 'x', 'notification_type' => 'qa_reply_received', 'url' => '/'],
        ]);

        $response = $this->actingAs($other)->post(route('notifications.markAsRead', $notification));

        $response->assertForbidden();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_already_read_notification_stays_read_and_still_redirects(): void
    {
        $user = User::factory()->student()->create();
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\QaReplyReceivedNotification',
            'data' => ['title' => 'x', 'message' => 'x', 'notification_type' => 'qa_reply_received', 'url' => '/qa-board/some-thread'],
            'read_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->post(route('notifications.markAsRead', $notification));

        $response->assertRedirect('/qa-board/some-thread');
    }
}
