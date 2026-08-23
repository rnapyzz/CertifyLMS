<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view(): void
    {
        $user = User::factory()->student()->create();
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\QaReplyReceivedNotification',
            'data' => ['title' => '通知タイトル', 'message' => '本文', 'notification_type' => 'qa_reply_received', 'url' => '/'],
        ]);

        $response = $this->actingAs($user)->get(route('notifications.show', $notification));

        $response->assertOk();
        $response->assertSee('通知タイトル');
    }

    public function test_other_user_is_forbidden(): void
    {
        $owner = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $notification = $owner->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\QaReplyReceivedNotification',
            'data' => ['title' => 'x', 'message' => 'x', 'notification_type' => 'qa_reply_received', 'url' => '/'],
        ]);

        $response = $this->actingAs($other)->get(route('notifications.show', $notification));

        $response->assertForbidden();
    }
}
