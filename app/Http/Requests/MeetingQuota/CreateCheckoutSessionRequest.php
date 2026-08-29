<?php

declare(strict_types=1);

namespace App\Http\Requests\MeetingQuota;

use App\Models\MeetingPack;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 追加面談パックの購入(Stripe Checkout Session 作成)リクエスト。
 *
 * `exists` ではなく published スコープ付きのカスタム存在チェックにすることで、
 * 「公開中でない面談パックは URL を直接指定しても購入できない」を満たす
 * (ルート自体は student + active-learning で保護済み、authorize は常に true)。
 */
class CreateCheckoutSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'meeting_pack_id' => [
                'required',
                'string',
                Rule::exists(MeetingPack::class, 'id')->where('status', 'published'),
            ],
        ];
    }
}
