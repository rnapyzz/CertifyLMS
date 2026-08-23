<?php

declare(strict_types=1);

namespace App\Http\Requests\EnrollmentGoal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 個人目標の更新リクエスト。受講生本人がタイトル / 詳細 / 目標期日を更新する。
 * 達成状態(achieved_at)は本フォームでは扱わない(達成マーク / 達成解除の専用エンドポイントから行う)。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('goal')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'target_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => '目標',
            'description' => '詳細',
            'target_date' => '目標期日',
        ];
    }
}
