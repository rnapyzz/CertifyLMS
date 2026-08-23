<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnrollmentNote>
 */
class EnrollmentNoteFactory extends Factory
{
    protected $model = EnrollmentNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'author_id' => User::factory()->coach(),
            'body' => fake()->randomElement([
                '最近 chat の返信が遅れがち。次回面談で学習時間を確保できているか確認したい。',
                'Q&A 掲示板でアルゴリズム分野の質問が続いている。次の面談で重点的にフォローする。',
                '模試の点数が伸び悩んでいる。苦手分野の演習を勧めた。',
                '順調に学習が進んでいる。特に懸念事項なし。',
            ]),
        ];
    }
}
