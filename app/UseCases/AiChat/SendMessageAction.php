<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Exceptions\AiChat\AiChatDailyLimitExceededException;
use App\Exceptions\AiChat\GeminiChatException;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use App\Services\GeminiChatService;

/**
 * 受講生の発言を永続化し、Gemini に問い合わせて AI の応答も永続化するユースケース。
 *
 * - 日次送信上限に達している場合は何も永続化せず `AiChatDailyLimitExceededException`(429)を投げる。
 * - 受講生の発言は AI 呼出の成否によらず必ず残す(「AI が一時的に失敗しても質問は残ってほしい」)。
 * - Gemini 呼出は DB トランザクションの外で行う(外部 API のレイテンシで DB ロックを長時間握らないため)。
 * - 会話の最初のやり取りが成功し、かつ auto_title_enabled であれば AI にタイトルを生成させる
 *   (失敗しても致命的ではないため `GeminiChatService::generateTitle` は例外を投げず null を返す)。
 */
final class SendMessageAction
{
    public function __construct(private readonly GeminiChatService $gemini) {}

    /**
     * @return array{userMessage: AiChatMessage, assistantMessage: AiChatMessage, upstreamStatus: ?int, titleUpdated: ?string}
     *
     * @throws AiChatDailyLimitExceededException
     */
    public function __invoke(User $user, AiChatConversation $conversation, string $content): array
    {
        if (AiChatMessage::dailyCountForUser($user) >= (int) config('ai-chat.daily_message_limit')) {
            throw new AiChatDailyLimitExceededException;
        }

        $isFirstExchange = ! $conversation->messages()->exists();
        $history = $this->buildHistory($conversation);

        $userMessage = AiChatMessage::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => AiChatMessageRole::User,
            'status' => AiChatMessageStatus::Completed,
            'content' => $content,
        ]);
        $conversation->forceFill(['last_message_at' => $userMessage->created_at])->save();

        $upstreamStatus = null;

        try {
            $result = $this->gemini->ask(
                systemPrompt: $this->buildSystemPrompt($conversation),
                history: $history,
                userMessage: $content,
            );

            $assistantMessage = AiChatMessage::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'role' => AiChatMessageRole::Assistant,
                'status' => AiChatMessageStatus::Completed,
                'content' => $result['content'],
                'model' => $result['model'],
                'input_tokens' => $result['input_tokens'],
                'output_tokens' => $result['output_tokens'],
                'response_time_ms' => $result['response_time_ms'],
            ]);
        } catch (GeminiChatException $e) {
            report($e);
            $upstreamStatus = $e->upstreamStatus();

            $assistantMessage = AiChatMessage::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'role' => AiChatMessageRole::Assistant,
                'status' => AiChatMessageStatus::Error,
                'content' => '',
                'error_detail' => $upstreamStatus !== null ? (string) $upstreamStatus : $e->getMessage(),
            ]);
        }

        $conversation->forceFill(['last_message_at' => $assistantMessage->created_at])->save();

        $titleUpdated = null;
        if ($isFirstExchange && $conversation->auto_title_enabled && $assistantMessage->status === AiChatMessageStatus::Completed) {
            $title = $this->gemini->generateTitle($content, $assistantMessage->content);
            if ($title !== null) {
                $conversation->forceFill(['title' => $title])->save();
                $titleUpdated = $title;
            }
        }

        return [
            'userMessage' => $userMessage,
            'assistantMessage' => $assistantMessage,
            'upstreamStatus' => $upstreamStatus,
            'titleUpdated' => $titleUpdated,
        ];
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function buildHistory(AiChatConversation $conversation): array
    {
        $limit = (int) config('ai-chat.history_message_limit', 10);

        return $conversation->messages()
            ->where('status', AiChatMessageStatus::Completed->value)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn (AiChatMessage $m) => [
                'role' => $m->role === AiChatMessageRole::User ? 'user' : 'model',
                'content' => $m->content,
            ])
            ->values()
            ->all();
    }

    private function buildSystemPrompt(AiChatConversation $conversation): string
    {
        $conversation->loadMissing('section', 'enrollment.certification');

        $lines = [
            'あなたは Certify LMS という資格学習プラットフォームの学習支援 AI です。',
            '受講生の質問に日本語で、わかりやすく簡潔に答えてください。',
            '資格試験の合否判定や断定的な保証は避け、あくまで学習の補助であることを踏まえて回答してください。',
        ];

        if ($conversation->section !== null) {
            $section = $conversation->section;
            $maxChars = (int) config('ai-chat.section_context_max_chars', 4000);
            $body = mb_substr((string) $section->body, 0, $maxChars);
            $lines[] = "受講生は現在、以下の教材を読んでいます。関連する質問にはこの教材の内容を踏まえて回答してください。\n"
                ."---\nタイトル: {$section->title}\n{$body}\n---";
        } elseif ($conversation->enrollment?->certification !== null) {
            $lines[] = "受講生は「{$conversation->enrollment->certification->name}」の資格取得を目指して学習中です。";
        }

        return implode("\n\n", $lines);
    }
}
