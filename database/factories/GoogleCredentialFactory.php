<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GoogleCredential;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleCredential>
 */
class GoogleCredentialFactory extends Factory
{
    protected $model = GoogleCredential::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->coach(),
            'access_token' => 'demo-access-token-'.fake()->uuid(),
            'refresh_token' => 'demo-refresh-token-'.fake()->uuid(),
            'token_expires_at' => now()->addHour(),
            'calendar_id' => 'primary',
            'connected_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ];
    }
}
