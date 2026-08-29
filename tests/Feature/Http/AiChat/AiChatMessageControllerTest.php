<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Exceptions\AiChat\GeminiChatException;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use App\Services\GeminiChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * AI 相談メッセージ送信(JSON 専用)を検証する。`GeminiChatService` は Mockery で差し替える。
 */
class AiChatMessageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_persists_user_and_assistant_messages_on_success(): void
    {
        $student = User::factory()->student()->create();
        $conversation = AiChatConversation::factory()->for($student)->create();

        $mock = Mockery::mock(GeminiChatService::class);
        $mock->shouldReceive('ask')->once()->andReturn([
            'content' => 'AI の回答です。',
            'model' => 'gemini-2.5-flash',
            'input_tokens' => 12,
            'output_tokens' => 34,
            'response_time_ms' => 800,
        ]);
        $mock->shouldReceive('generateTitle')->once()->andReturn('自動タイトル');
        $this->app->instance(GeminiChatService::class, $mock);

        $response = $this->actingAs($student)->postJson(
            route('ai-chat.conversations.messages.store', $conversation),
            ['content' => '質問です']
        );

        $response->assertOk();
        $response->assertJsonPath('user_message.content', '質問です');
        $response->assertJsonPath('assistant_message.content', 'AI の回答です。');
        $response->assertJsonPath('assistant_message.status', 'completed');
        $response->assertJsonPath('conversation.title', '自動タイトル');
        $this->assertSame('自動タイトル', $conversation->fresh()->title);
    }

    public function test_store_returns_502_with_upstream_status_on_gemini_failure(): void
    {
        $student = User::factory()->student()->create();
        $conversation = AiChatConversation::factory()->for($student)->create();

        $mock = Mockery::mock(GeminiChatService::class);
        $mock->shouldReceive('ask')->once()->andThrow(new GeminiChatException('failed', upstreamStatus: 503));
        $this->app->instance(GeminiChatService::class, $mock);

        $response = $this->actingAs($student)->postJson(
            route('ai-chat.conversations.messages.store', $conversation),
            ['content' => '質問です']
        );

        $response->assertStatus(502);
        $response->assertJsonPath('upstream_status', 503);

        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => '質問です',
        ]);
        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'status' => 'error',
            'error_detail' => '503',
        ]);
    }

    public function test_store_returns_429_when_daily_limit_reached_without_persisting(): void
    {
        $student = User::factory()->student()->create();
        config(['ai-chat.daily_message_limit' => 1]);
        $conversation = AiChatConversation::factory()->for($student)->create();
        AiChatMessage::factory()->for($conversation, 'conversation')->create(['user_id' => $student->id]);

        $mock = Mockery::mock(GeminiChatService::class);
        $mock->shouldNotReceive('ask');
        $this->app->instance(GeminiChatService::class, $mock);

        $response = $this->actingAs($student)->postJson(
            route('ai-chat.conversations.messages.store', $conversation),
            ['content' => 'もう一つの質問']
        );

        $response->assertStatus(429);
        $this->assertDatabaseMissing('ai_chat_messages', ['content' => 'もう一つの質問']);
    }

    public function test_store_validates_empty_content(): void
    {
        $student = User::factory()->student()->create();
        $conversation = AiChatConversation::factory()->for($student)->create();

        $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.messages.store', $conversation), ['content' => ''])
            ->assertStatus(422);
    }

    public function test_store_denies_non_owner(): void
    {
        $owner = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $conversation = AiChatConversation::factory()->for($owner)->create();

        $mock = Mockery::mock(GeminiChatService::class);
        $mock->shouldNotReceive('ask');
        $this->app->instance(GeminiChatService::class, $mock);

        $this->actingAs($other)
            ->postJson(route('ai-chat.conversations.messages.store', $conversation), ['content' => 'x'])
            ->assertForbidden();
    }

    public function test_store_does_not_regenerate_title_for_second_exchange(): void
    {
        $student = User::factory()->student()->create();
        $conversation = AiChatConversation::factory()->for($student)->create(['auto_title_enabled' => true]);
        AiChatMessage::factory()->for($conversation, 'conversation')->create(['user_id' => $student->id]);
        AiChatMessage::factory()->fromAssistant()->for($conversation, 'conversation')->create(['user_id' => $student->id]);

        $mock = Mockery::mock(GeminiChatService::class);
        $mock->shouldReceive('ask')->once()->andReturn([
            'content' => '2 回目の回答',
            'model' => 'gemini-2.5-flash',
            'input_tokens' => 1,
            'output_tokens' => 1,
            'response_time_ms' => 100,
        ]);
        $mock->shouldNotReceive('generateTitle');
        $this->app->instance(GeminiChatService::class, $mock);

        $response = $this->actingAs($student)->postJson(
            route('ai-chat.conversations.messages.store', $conversation),
            ['content' => '2 回目の質問']
        );

        $response->assertOk();
        $response->assertJsonMissingPath('conversation');
    }
}
