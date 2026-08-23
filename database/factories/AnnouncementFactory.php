<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->randomElement([
                'メンテナンスのお知らせ',
                '教材アップデートのお知らせ',
                '学習キャンペーンのお知らせ',
            ]),
            'body' => fake()->realText(200),
            'target_type' => AnnouncementTargetType::AllStudents,
            'target_certification_id' => null,
            'target_user_id' => null,
            'created_by_user_id' => User::factory()->admin(),
            'dispatched_count' => 0,
            'dispatched_at' => now(),
        ];
    }

    public function allStudents(): static
    {
        return $this->state(fn () => [
            'target_type' => AnnouncementTargetType::AllStudents,
            'target_certification_id' => null,
            'target_user_id' => null,
        ]);
    }

    public function forCertification(string $certificationId): static
    {
        return $this->state(fn () => [
            'target_type' => AnnouncementTargetType::Certification,
            'target_certification_id' => $certificationId,
            'target_user_id' => null,
        ]);
    }

    public function forUser(string $userId): static
    {
        return $this->state(fn () => [
            'target_type' => AnnouncementTargetType::User,
            'target_certification_id' => null,
            'target_user_id' => $userId,
        ]);
    }
}
