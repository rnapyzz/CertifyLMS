<?php

declare(strict_types=1);

namespace App\Http\Requests\EnrollmentNote;

use App\Models\EnrollmentNote;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * コーチメモの新規作成リクエスト。受講登録詳細画面内のフォームから、担当コーチ / 管理者が投稿する。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [EnrollmentNote::class, $this->route('enrollment')]) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => 'メモ本文',
        ];
    }
}
