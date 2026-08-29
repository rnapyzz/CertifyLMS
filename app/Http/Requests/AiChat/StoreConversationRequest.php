<?php

declare(strict_types=1);

namespace App\Http\Requests\AiChat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AI 相談会話の新規作成(またはウィジェットの section_id 一致による再利用)。
 *
 * - source=full-screen: フル画面の「新しい会話」モーダルからの plain form POST。message は任意
 *   (入力があれば会話作成と同期で AI に問い合わせて最初のやり取りまで作る)。section_id は持たない。
 * - source=widget: フローティングウィジェットからの fetch(JSON)。section_id があれば
 *   その教材文脈で会話を作成(または同一 section の既存会話を再利用)、無ければ全般相談。
 *   message は送らない(空の会話だけ作り、最初のメッセージは別途 messages.store で送る)。
 *
 * ルート自体が student + active-learning で保護されているため authorize は常に true。
 */
class StoreConversationRequest extends FormRequest
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
            'source' => ['required', 'string', 'in:widget,full-screen'],
            'section_id' => ['nullable', 'string', 'exists:sections,id'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
