<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarkAllAsReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_all_own_unread_notifications_as_read(): void
    {
        $user = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $this->createNotification($user);
        $this->createNotification($user);
        $othersNotification = $this->createNotification($other);

        $response = $this->actingAs($user)->post(route('notifications.markAllAsRead'));

        $response->assertRedirect(route('notifications.index'));
        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
        $this->assertNull($othersNotification->fresh()->read_at, '他人の未読通知は影響を受けないはず');
    }

    private function createNotification(User $user): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\QaReplyReceivedNotification',
            'data' => ['title' => 'x', 'message' => 'x', 'notification_type' => 'qa_reply_received', 'url' => '/'],
        ]);
    }
}
