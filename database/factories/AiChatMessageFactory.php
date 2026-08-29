<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiChatMessage>
 */
class AiChatMessageFactory extends Factory
{
    protected $model = AiChatMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => AiChatConversation::factory(),
            'user_id' => User::factory()->student(),
            'role' => AiChatMessageRole::User,
            'status' => AiChatMessageStatus::Completed,
            'content' => fake()->realText(120),
        ];
    }

    public function fromAssistant(): static
    {
        return $this->state(fn () => [
            'role' => AiChatMessageRole::Assistant,
            'status' => AiChatMessageStatus::Completed,
            'content' => fake()->realText(200),
            'model' => 'gemini-3.6-flash',
            'input_tokens' => fake()->numberBetween(50, 300),
            'output_tokens' => fake()->numberBetween(50, 300),
            'response_time_ms' => fake()->numberBetween(400, 3000),
        ]);
    }

    public function errored(): static
    {
        return $this->state(fn () => [
            'role' => AiChatMessageRole::Assistant,
            'status' => AiChatMessageStatus::Error,
            'content' => '',
            'error_detail' => '503',
        ]);
    }
}
