<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\AiChat\AiChatDailyLimitExceededException;
use App\Http\Requests\AiChat\StoreConversationRequest;
use App\Http\Requests\AiChat\UpdateConversationRequest;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\UseCases\AiChat\DestroyConversationAction;
use App\UseCases\AiChat\SendMessageAction;
use App\UseCases\AiChat\StoreConversationAction;
use App\UseCases\AiChat\UpdateConversationAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AI 相談(Gemini チャットボット)の会話管理 Controller。
 *
 * - index: 直近の会話へ redirect、0 件なら empty-state
 * - store: フル画面の「新しい会話」モーダル(plain form POST、message 任意で同期 AI 応答まで待つ)と
 *   フローティングウィジェット(JSON、section_id での再利用判定あり)の 2 経路を
 *   `$request->expectsJson()` で振り分ける
 * - show: 同じく HTML(フル画面表示) / JSON(ウィジェットの履歴復元) を振り分ける
 * - update / destroy: 所有者本人のみ(Policy)
 */
class AiChatConversationController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $latest = $request->user()->aiChatConversations()
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->first();

        if ($latest !== null) {
            return redirect()->route('ai-chat.conversations.show', $latest);
        }

        return view('ai-chat.empty-state');
    }

    public function store(
        StoreConversationRequest $request,
        StoreConversationAction $storeConversation,
        SendMessageAction $sendMessage,
    ): RedirectResponse|JsonResponse {
        $user = $request->user();
        $validated = $request->validated();
        $message = trim((string) ($validated['message'] ?? ''));

        if ($message !== '' && AiChatMessage::dailyCountForUser($user) >= (int) config('ai-chat.daily_message_limit')) {
            return redirect()->back()->withInput()
                ->with('error', '本日の質問送信回数の上限に達しました。日付が変わってから再度お試しください。');
        }

        ['conversation' => $conversation, 'created' => $created] = $storeConversation($user, $validated);

        if ($message !== '') {
            try {
                $sendMessage($user, $conversation, $message);
            } catch (AiChatDailyLimitExceededException) {
                return redirect()->back()->withInput()
                    ->with('error', '本日の質問送信回数の上限に達しました。日付が変わってから再度お試しください。');
            }
        }

        if ($request->expectsJson()) {
            return response()->json(['conversation' => ['id' => $conversation->id]], $created ? 201 : 200);
        }

        return redirect()->route('ai-chat.conversations.show', $conversation);
    }

    public function show(AiChatConversation $conversation, Request $request): View|JsonResponse
    {
        $this->authorize('view', $conversation);

        if ($request->expectsJson()) {
            $messages = $conversation->messages()->get();

            return response()->json([
                'messages' => $messages->map(fn (AiChatMessage $m) => $m->toChatApiArray())->all(),
            ]);
        }

        $conversation->load(['messages', 'section', 'enrollment.certification']);

        return view('ai-chat.show', ['conversation' => $conversation]);
    }

    public function update(
        UpdateConversationRequest $request,
        AiChatConversation $conversation,
        UpdateConversationAction $action,
    ): RedirectResponse {
        $action($conversation, $request->validated()['title']);

        return redirect()
            ->route('ai-chat.conversations.show', $conversation)
            ->with('success', 'タイトルを更新しました。');
    }

    public function destroy(AiChatConversation $conversation, DestroyConversationAction $action): RedirectResponse
    {
        $this->authorize('delete', $conversation);

        $action($conversation);

        return redirect()
            ->route('ai-chat.index')
            ->with('success', 'この会話を削除しました。');
    }
}
