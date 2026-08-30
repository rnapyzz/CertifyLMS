<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * TopBar 通知ポップオーバー向け JSON API(Sanctum SPA Cookie 認証)を検証する。
 * `actingAs()` は web ガードのセッションを張るため、`auth:sanctum`(guard=['web'])はそのまま通る。
 */
class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeNotification(User $notifiable, array $data = []): DatabaseNotification
    {
        return $notifiable->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\QaReplyReceivedNotification',
            'data' => array_merge([
                'title' => 'テスト通知',
                'message' => '本文プレビュー',
                'notification_type' => 'qa_reply_received',
                'url' => '/qa-board/1',
            ], $data),
        ]);
    }

    public function test_index_returns_only_own_notifications_with_unread_count(): void
    {
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();

        $unread = $this->makeNotification($student, ['title' => '未読通知']);
        $read = $this->makeNotification($student, ['title' => '既読通知']);
        $read->markAsRead();
        $this->makeNotification($other, ['title' => '他人の通知']);

        $response = $this->actingAs($student)->getJson('/api/v1/notifications');

        $response->assertOk();
        $response->assertJsonCount(2, 'notifications');
        $response->assertJsonPath('unread_count', 1);
        $titles = collect($response->json('notifications'))->pluck('title')->all();
        $this->assertContains('未読通知', $titles);
        $this->assertContains('既読通知', $titles);
        $this->assertNotContains('他人の通知', $titles);
    }

    public function test_index_notification_shape_includes_target_url_and_unread_flag(): void
    {
        $student = User::factory()->student()->create();
        $this->makeNotification($student, ['title' => '質問への返信', 'message' => '本文', 'url' => '/qa-board/42']);

        $response = $this->actingAs($student)->getJson('/api/v1/notifications');

        $response->assertOk();
        $response->assertJsonPath('notifications.0.title', '質問への返信');
        $response->assertJsonPath('notifications.0.message', '本文');
        $response->assertJsonPath('notifications.0.target_url', '/qa-board/42');
        $response->assertJsonPath('notifications.0.unread', true);
        $response->assertJsonStructure(['notifications' => [['id', 'title', 'message', 'target_url', 'created_at_human', 'unread']]]);
    }

    public function test_index_falls_back_to_notifications_show_route_when_no_url_in_data(): void
    {
        $student = User::factory()->student()->create();
        $notification = $this->makeNotification($student, ['url' => null]);

        $response = $this->actingAs($student)->getJson('/api/v1/notifications');

        $response->assertOk();
        $response->assertJsonPath('notifications.0.target_url', route('notifications.show', $notification));
    }

    public function test_index_is_forbidden_for_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/notifications')
            ->assertForbidden();
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }

    public function test_coach_can_access_index(): void
    {
        $coach = User::factory()->coach()->create();
        $this->makeNotification($coach);

        $this->actingAs($coach)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'notifications');
    }

    public function test_mark_as_read_marks_own_notification_and_returns_updated_unread_count(): void
    {
        $student = User::factory()->student()->create();
        $notification = $this->makeNotification($student);
        $this->makeNotification($student);

        $response = $this->actingAs($student)->postJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertOk();
        $response->assertJsonPath('unread_count', 1);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_as_read_is_idempotent(): void
    {
        $student = User::factory()->student()->create();
        $notification = $this->makeNotification($student);
        $notification->markAsRead();
        $originalReadAt = $notification->fresh()->read_at;

        $response = $this->actingAs($student)->postJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertOk();
        $this->assertSame(
            $originalReadAt->toIso8601String(),
            $notification->fresh()->read_at->toIso8601String(),
        );
    }

    public function test_mark_as_read_denies_other_users_notification(): void
    {
        $owner = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $notification = $this->makeNotification($owner);

        $this->actingAs($other)
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read_marks_only_own_unread_notifications(): void
    {
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $mine1 = $this->makeNotification($student);
        $mine2 = $this->makeNotification($student);
        $othersUnread = $this->makeNotification($other);

        $response = $this->actingAs($student)->postJson('/api/v1/notifications/read-all');

        $response->assertOk();
        $response->assertJsonPath('unread_count', 0);
        $this->assertNotNull($mine1->fresh()->read_at);
        $this->assertNotNull($mine2->fresh()->read_at);
        $this->assertNull($othersUnread->fresh()->read_at);
    }
}
