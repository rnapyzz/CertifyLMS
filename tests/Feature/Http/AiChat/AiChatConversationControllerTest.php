<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Section;
use App\Models\User;
use App\Services\GeminiChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * AI 相談の会話管理エンドポイントを検証する。実際の Gemini API へは通信せず
 * `GeminiChatService` を Mockery で差し替える(`GoogleCalendarService` のテストと同じ方針)。
 */
class AiChatConversationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_redirects_to_latest_conversation(): void
    {
        $student = User::factory()->student()->create();
        AiChatConversation::factory()->for($student)->create(['last_message_at' => now()->subDay()]);
        $latest = AiChatConversation::factory()->for($student)->create(['last_message_at' => now()]);

        $this->actingAs($student)
            ->get(route('ai-chat.index'))
            ->assertRedirect(route('ai-chat.conversations.show', $latest));
    }

    public function test_index_shows_empty_state_when_no_conversations(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('ai-chat.index'))
            ->assertOk()
            ->assertViewIs('ai-chat.empty-state');
    }

    public function test_index_is_blocked_for_non_student(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)
            ->get(route('ai-chat.index'))
            ->assertForbidden();
    }

    public function test_store_full_screen_without_message_creates_empty_conversation_and_redirects(): void
    {
        $student = User::factory()->student()->create();

        $mock = Mockery::mock(GeminiChatService::class);
        $mock->shouldNotReceive('ask');
        $this->app->instance(GeminiChatService::class, $mock);

        $response = $this->actingAs($student)->post(route('ai-chat.conversations.store'), [
            'source' => 'full-screen',
        ]);

        $conversation = AiChatConversation::query()->where('user_id', $student->id)->firstOrFail();
        $response->assertRedirect(route('ai-chat.conversations.show', $conversation));
        $this->assertDatabaseCount('ai_chat_messages', 0);
    }

    public function test_store_full_screen_with_message_answers_synchronously_before_redirect(): void
    {
        $student = User::factory()->student()->create();

        $mock = Mockery::mock(GeminiChatService::class);
        $mock->shouldReceive('ask')->once()->andReturn([
            'content' => 'AI からの回答です。',
            'model' => 'gemini-2.5-flash',
            'input_tokens' => 10,
            'output_tokens' => 20,
            'response_time_ms' => 500,
        ]);
        $mock->shouldReceive('generateTitle')->once()->andReturn('自動生成タイトル');
        $this->app->instance(GeminiChatService::class, $mock);

        $response = $this->actingAs($student)->post(route('ai-chat.conversations.store'), [
            'source' => 'full-screen',
            'message' => '最初の質問です',
        ]);

        $conversation = AiChatConversation::query()->where('user_id', $student->id)->firstOrFail();
        $response->assertRedirect(route('ai-chat.conversations.show', $conversation));
        $this->assertSame('自動生成タイトル', $conversation->fresh()->title);
        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => '最初の質問です',
        ]);
        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'AI からの回答です。',
            'status' => 'completed',
        ]);
    }

    public function test_store_widget_without_section_creates_new_conversation_as_json(): void
    {
        $student = User::factory()->student()->create();

        $mock = Mockery::mock(GeminiChatService::class);
        $mock->shouldNotReceive('ask');
        $this->app->instance(GeminiChatService::class, $mock);

        $response = $this->actingAs($student)->postJson(route('ai-chat.conversations.store'), [
            'source' => 'widget',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['conversation' => ['id']]);
    }

    public function test_store_widget_reuses_existing_conversation_for_same_section(): void
    {
        $student = User::factory()->student()->create();
        $section = Section::factory()->create();
        $existing = AiChatConversation::factory()->for($student)->create(['section_id' => $section->id]);

        $response = $this->actingAs($student)->postJson(route('ai-chat.conversations.store'), [
            'source' => 'widget',
            'section_id' => $section->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['conversation' => ['id' => $existing->id]]);
        $this->assertDatabaseCount('ai_chat_conversations', 1);
    }

    public function test_store_full_screen_with_message_is_rejected_when_daily_limit_reached(): void
    {
        $student = User::factory()->student()->create();
        config(['ai-chat.daily_message_limit' => 1]);
        $conversation = AiChatConversation::factory()->for($student)->create();
        AiChatMessage::factory()->for($conversation, 'conversation')->create([
            'user_id' => $student->id,
        ]);

        $mock = Mockery::mock(GeminiChatService::class);
        $mock->shouldNotReceive('ask');
        $this->app->instance(GeminiChatService::class, $mock);

        $response = $this->actingAs($student)->post(route('ai-chat.conversations.store'), [
            'source' => 'full-screen',
            'message' => 'もう一つ質問',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('ai_chat_conversations', 1);
    }

    public function test_show_renders_conversation_for_owner(): void
    {
        $student = User::factory()->student()->create();
        $conversation = AiChatConversation::factory()->for($student)->create();

        $this->actingAs($student)
            ->get(route('ai-chat.conversations.show', $conversation))
            ->assertOk()
            ->assertViewIs('ai-chat.show');
    }

    public function test_show_returns_json_messages_when_requested(): void
    {
        $student = User::factory()->student()->create();
        $conversation = AiChatConversation::factory()->for($student)->create();
        AiChatMessage::factory()->for($conversation, 'conversation')->create(['user_id' => $student->id]);

        $response = $this->actingAs($student)->getJson(route('ai-chat.conversations.show', $conversation));

        $response->assertOk();
        $response->assertJsonCount(1, 'messages');
        $response->assertJsonStructure(['messages' => [['id', 'role', 'content', 'status', 'model', 'response_time_ms', 'output_tokens', 'created_at']]]);
    }

    public function test_show_denies_non_owner(): void
    {
        $owner = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $conversation = AiChatConversation::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('ai-chat.conversations.show', $conversation))
            ->assertForbidden();
    }

    public function test_update_changes_title_and_disables_auto_title(): void
    {
        $student = User::factory()->student()->create();
        $conversation = AiChatConversation::factory()->for($student)->create(['auto_title_enabled' => true]);

        $response = $this->actingAs($student)->patch(route('ai-chat.conversations.update', $conversation), [
            'title' => '新しいタイトル',
        ]);

        $response->assertRedirect(route('ai-chat.conversations.show', $conversation));
        $conversation->refresh();
        $this->assertSame('新しいタイトル', $conversation->title);
        $this->assertFalse($conversation->auto_title_enabled);
    }

    public function test_update_denies_non_owner(): void
    {
        $owner = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $conversation = AiChatConversation::factory()->for($owner)->create();

        $this->actingAs($other)
            ->patch(route('ai-chat.conversations.update', $conversation), ['title' => 'x'])
            ->assertForbidden();
    }

    public function test_destroy_deletes_conversation_and_cascades_messages(): void
    {
        $student = User::factory()->student()->create();
        $conversation = AiChatConversation::factory()->for($student)->create();
        AiChatMessage::factory()->for($conversation, 'conversation')->create(['user_id' => $student->id]);

        $response = $this->actingAs($student)->delete(route('ai-chat.conversations.destroy', $conversation));

        $response->assertRedirect(route('ai-chat.index'));
        $this->assertDatabaseMissing('ai_chat_conversations', ['id' => $conversation->id]);
        $this->assertDatabaseCount('ai_chat_messages', 0);
    }

    public function test_destroy_denies_non_owner(): void
    {
        $owner = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $conversation = AiChatConversation::factory()->for($owner)->create();

        $this->actingAs($other)
            ->delete(route('ai-chat.conversations.destroy', $conversation))
            ->assertForbidden();
    }
}
