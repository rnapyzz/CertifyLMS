<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_only_own_notifications(): void
    {
        $user = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $this->createNotification($user, ['title' => '自分宛の通知']);
        $this->createNotification($other, ['title' => '他人宛の通知']);

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertOk();
        $response->assertSee('自分宛の通知');
        $response->assertDontSee('他人宛の通知');
    }

    public function test_unread_tab_excludes_read_notifications(): void
    {
        $user = User::factory()->student()->create();
        $this->createNotification($user, ['title' => '未読通知'], readAt: null);
        $this->createNotification($user, ['title' => '既読通知'], readAt: now());

        $response = $this->actingAs($user)->get(route('notifications.index', ['tab' => 'unread']));

        $response->assertOk();
        $response->assertSee('未読通知');
        $response->assertDontSee('既読通知');
    }

    public function test_all_roles_can_access_including_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('notifications.index'));

        $response->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    private function createNotification(User $user, array $data, ?Carbon $readAt = null): void
    {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\QaReplyReceivedNotification',
            'data' => array_merge([
                'title' => 'title',
                'message' => 'message',
                'notification_type' => 'qa_reply_received',
                'url' => '/',
            ], $data),
            'read_at' => $readAt,
        ]);
    }
}
