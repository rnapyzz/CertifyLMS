<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Enums\UserStatus;
use App\Events\ChatMessagePosted;
use App\Listeners\SendChatMessageNotification;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use App\Services\NotificationEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendChatMessageNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifies_other_room_members_but_not_sender(): void
    {
        Notification::fake();

        $room = ChatRoom::factory()->create();
        $sender = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $sender->id]);
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $coach->id]);
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $otherStudent->id]);
        $message = ChatMessage::factory()->create(['chat_room_id' => $room->id, 'sender_user_id' => $sender->id]);

        (new SendChatMessageNotification(new NotificationEligibilityService))->handle(new ChatMessagePosted($message));

        Notification::assertSentTo($coach, ChatMessageReceivedNotification::class);
        Notification::assertSentTo($otherStudent, ChatMessageReceivedNotification::class);
        Notification::assertNotSentTo($sender, ChatMessageReceivedNotification::class);
    }

    public function test_does_not_notify_ineligible_member(): void
    {
        Notification::fake();

        $room = ChatRoom::factory()->create();
        $sender = User::factory()->student()->inProgress()->create();
        $graduatedCoach = User::factory()->coach()->create(['status' => UserStatus::Graduated->value]);
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $sender->id]);
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $graduatedCoach->id]);
        $message = ChatMessage::factory()->create(['chat_room_id' => $room->id, 'sender_user_id' => $sender->id]);

        (new SendChatMessageNotification(new NotificationEligibilityService))->handle(new ChatMessagePosted($message));

        Notification::assertNothingSent();
    }
}
