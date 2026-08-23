<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Chat;

use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use App\UseCases\Chat\StoreMessageAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * StoreMessageAction が実際に ChatMessagePosted イベント経由で送信者以外の参加者へ通知を発火することを
 * 検証する(S-B-04: 通知基盤の配線確認)。
 */
class ChatMessageNotificationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_posting_a_message_notifies_other_room_members(): void
    {
        Notification::fake();

        $sender = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($sender)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $sender->id]);
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $coach->id]);

        app(StoreMessageAction::class)($sender, $room, ['body' => 'こんにちは']);

        Notification::assertSentTo($coach, ChatMessageReceivedNotification::class);
        Notification::assertNotSentTo($sender, ChatMessageReceivedNotification::class);
    }
}
