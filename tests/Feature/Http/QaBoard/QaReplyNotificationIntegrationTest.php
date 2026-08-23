<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * QaReplyController::store が実際に QaReplyPosted イベント経由で通知を発火することを検証する
 * (S-B-04: 通知基盤の配線確認)。
 */
class QaReplyNotificationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_posting_a_reply_notifies_thread_author(): void
    {
        Notification::fake();

        $cert = Certification::factory()->published()->create();
        $author = User::factory()->student()->inProgress()->create();
        $replier = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($cert)->for($author)->create();

        $this->actingAs($replier)->post(route('qa-board.replies.store', $thread), [
            'body' => '回答本文です。',
        ])->assertRedirect();

        Notification::assertSentTo($author, QaReplyReceivedNotification::class);
    }
}
