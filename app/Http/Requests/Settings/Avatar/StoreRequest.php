<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings\Avatar;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * アイコン画像のアップロードリクエスト。png / jpg / jpeg / webp の 2MB 以下に制限する
 * (教材内画像アップロード `SectionImage\StoreRequest` と同じ制限値)。
 */
class StoreRequest extends FormRequest
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
        return [
            'avatar' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'avatar' => 'アイコン画像',
        ];
    }
}
