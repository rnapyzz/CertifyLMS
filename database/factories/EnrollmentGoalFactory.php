<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnrollmentGoal>
 */
class EnrollmentGoalFactory extends Factory
{
    protected $model = EnrollmentGoal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'title' => fake()->randomElement([
                '過去問 5 年分を解き終える',
                '苦手分野の演習を毎日 30 分続ける',
                '模試で合格ラインを超える',
                '教材を最後まで読み終える',
                '週 3 回の学習ペースを維持する',
            ]),
            'description' => fake()->optional()->sentence(),
            'target_date' => fake()->optional()->dateTimeBetween('now', '+3 months'),
            'achieved_at' => null,
        ];
    }

    public function achieved(): static
    {
        return $this->state(fn () => [
            'achieved_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }
}
