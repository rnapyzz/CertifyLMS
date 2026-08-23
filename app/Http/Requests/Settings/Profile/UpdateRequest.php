<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings\Profile;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * プロフィール情報(氏名 / 自己紹介 / 固定面談 URL)の更新リクエスト。
 * 常に本人自身の情報のみを対象とする(他ユーザーを操作対象にする route パラメータを持たない)ため、
 * 認可は `auth` ミドルウェアのみで完結する。メールアドレスはこの画面からは更新しない。
 *
 * `meeting_url` はコーチのみが持つフィールドのため、コーチ以外のリクエストでは検証対象にしない
 * (UI にも入力欄が現れないが、直接 POST された場合も無視する)。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:1000'],
        ];

        if ($this->user()?->role === UserRole::Coach) {
            $rules['meeting_url'] = ['nullable', 'url', 'max:500'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '氏名',
            'bio' => '自己紹介',
            'meeting_url' => '固定面談 URL',
        ];
    }
}
