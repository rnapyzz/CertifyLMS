<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AiChatMessageStatus;
use App\Http\Requests\AiChat\StoreAiChatMessageRequest;
use App\Models\AiChatConversation;
use App\UseCases\AiChat\SendMessageAction;
use Illuminate\Http\JsonResponse;

/**
 * AI 相談メッセージ送信(JSON 専用。フル画面 / ウィジェットいずれも `resources/js/ai-chat/chat-client.js`
 * 経由の fetch から呼ばれ、plain form での POST 経路は持たない)。
 *
 * 日次送信上限超過は `App\Exceptions\AiChat\AiChatDailyLimitExceededException`(429)が
 * catch されずそのまま伝播し、Laravel 標準の JSON エラー応答になる(クライアントはボディを読まない)。
 */
class AiChatMessageController extends Controller
{
    public function store(
        StoreAiChatMessageRequest $request,
        AiChatConversation $conversation,
        SendMessageAction $action,
    ): JsonResponse {
        $result = $action($request->user(), $conversation, $request->validated()['content']);

        if ($result['assistantMessage']->status === AiChatMessageStatus::Error) {
            return response()->json([
                'message' => 'AI からの応答取得に失敗しました。',
                'upstream_status' => $result['upstreamStatus'],
            ], 502);
        }

        $payload = [
            'user_message' => $result['userMessage']->toChatApiArray(),
            'assistant_message' => $result['assistantMessage']->toChatApiArray(),
        ];

        if ($result['titleUpdated'] !== null) {
            $payload['conversation'] = ['title' => $result['titleUpdated']];
        }

        return response()->json($payload);
    }
}
