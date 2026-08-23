<?php

declare(strict_types=1);

namespace App\Http\Requests\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Enums\UserRole;
use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 管理者お知らせの新規配信リクエスト。配信対象タイプに応じて対象資格 / 対象ユーザーの
 * どちらか一方のみを受け付ける(全受講生指定時はどちらも空でよい)。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Announcement::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'target_type' => ['required', Rule::enum(AnnouncementTargetType::class)],
            'target_certification_id' => [
                'required_if:target_type,'.AnnouncementTargetType::Certification->value,
                'prohibited_unless:target_type,'.AnnouncementTargetType::Certification->value,
                'nullable',
                'ulid',
                Rule::exists('certifications', 'id'),
            ],
            'target_user_id' => [
                'required_if:target_type,'.AnnouncementTargetType::User->value,
                'prohibited_unless:target_type,'.AnnouncementTargetType::User->value,
                'nullable',
                'ulid',
                Rule::exists('users', 'id')->where('role', UserRole::Student->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'body' => '本文',
            'target_type' => '配信対象',
            'target_certification_id' => '対象資格',
            'target_user_id' => '対象受講生',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_certification_id.required_if' => '配信対象が「資格指定」の場合は対象資格を選択してください。',
            'target_certification_id.prohibited_unless' => '配信対象が「資格指定」以外の場合は対象資格を指定できません。',
            'target_user_id.required_if' => '配信対象が「ユーザー指定」の場合は対象受講生を選択してください。',
            'target_user_id.prohibited_unless' => '配信対象が「ユーザー指定」以外の場合は対象受講生を指定できません。',
        ];
    }
}
