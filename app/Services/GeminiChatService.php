<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AiChat\GeminiChatException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Gemini API (Generative Language API, v1beta generateContent) との通信を集約する Service。
 *
 * 公式 PHP SDK が存在しないため Laravel `Http::` facade(Guzzle)で REST を直接叩く。
 *
 * `ask()` は「AI 応答そのものが受講生への回答」という機能の根幹のため、`GoogleCalendarService` の
 * ような握りつぶし(catch + report() + null 返却)はしない。失敗理由(HTTP ステータス)を呼出元
 * (`SendMessageAction`)が知る必要がある(AiChatMessage.error_detail への保存 / JSON 応答の
 * upstream_status に使うため)ので、`GeminiChatException` として投げる。
 *
 * 一方 `generateTitle()` はタイトル自動生成という補助機能であり、失敗しても会話や応答自体は
 * 何も損なわれないため、例外を投げず null を返して呼出元に truncate フォールバックを促す
 * (`GoogleCalendarService::busyIntervals` 等と同じ「握りつぶし」方針)。
 *
 * `final` 不採用: 実際に外部 API 通信を行う Service のため、テストでは `Mockery::mock` で
 * 差し替える(`GoogleCalendarService` と同じ理由)。
 *
 * 一時的な障害(接続失敗 / 5xx)は `ask()` 内部で最大 {@see self::MAX_ATTEMPTS} 回まで自動リトライする
 * (要件確認済み: 呼出元をまたいだ「次のリクエストは独立して成功しうる」ではなく、1 回の `ask()` 呼び出し
 * 内で復旧できる必要がある)。4xx(認証エラー・不正リクエスト等)はリトライしても成功しないため対象外。
 */
class GeminiChatService
{
    private const API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    /** 初回 + リトライ 1 回の計 2 回まで試行する。 */
    private const MAX_ATTEMPTS = 2;

    /** リトライ前の待機時間(ミリ秒)。 */
    private const RETRY_DELAY_MS = 300;

    /**
     * @param list<array{role: string, content: string}> $history 直近の会話履歴(user/model ロール、古い順)
     *
     * @return array{content: string, model: string, input_tokens: ?int, output_tokens: ?int, response_time_ms: int}
     *
     * @throws GeminiChatException
     */
    public function ask(string $systemPrompt, array $history, string $userMessage): array
    {
        $apiKey = (string) config('services.gemini.api_key');
        $model = (string) config('ai-chat.gemini.model', 'gemini-3.6-flash');

        if ($apiKey === '') {
            throw new GeminiChatException('GEMINI_API_KEY が設定されていません。');
        }

        $contents = [];
        foreach ($history as $turn) {
            $contents[] = [
                'role' => $turn['role'],
                'parts' => [['text' => $turn['content']]],
            ];
        }
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $userMessage]],
        ];

        $startedAt = microtime(true);

        $response = null;
        $connectionException = null;
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $connectionException = null;

            try {
                // API キーはクエリパラメータではなく `X-Goog-Api-Key` ヘッダで渡す。接続失敗時の
                // 例外メッセージには失敗した URL がそのまま含まれ report() 経由でログに残るため、
                // クエリパラメータに載せるとログにキーが平文で漏れてしまう。
                $response = Http::timeout((int) config('ai-chat.gemini.timeout', 20))
                    ->withHeaders(['X-Goog-Api-Key' => $apiKey])
                    ->post(self::API_BASE_URL."/{$model}:generateContent", [
                        'systemInstruction' => [
                            'parts' => [['text' => $systemPrompt]],
                        ],
                        'contents' => $contents,
                    ]);
            } catch (ConnectionException $e) {
                $connectionException = $e;
            }

            // 一時的な障害(接続失敗 or 5xx)以外は即座に諦める(4xx をリトライしても無駄なため)。
            $isTransientFailure = $connectionException !== null || $response?->serverError() === true;
            if (! $isTransientFailure || $attempt === self::MAX_ATTEMPTS) {
                break;
            }

            usleep(self::RETRY_DELAY_MS * 1000);
        }

        if ($connectionException !== null) {
            throw new GeminiChatException('Gemini API への接続に失敗しました。', previous: $connectionException);
        }

        $responseTimeMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response->failed()) {
            throw new GeminiChatException(
                "Gemini API がエラーを返しました(HTTP {$response->status()})。",
                upstreamStatus: $response->status(),
            );
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (! is_string($text) || $text === '') {
            throw new GeminiChatException('Gemini API から有効な応答本文を取得できませんでした。', upstreamStatus: $response->status());
        }

        return [
            'content' => $text,
            'model' => $model,
            'input_tokens' => $response->json('usageMetadata.promptTokenCount'),
            'output_tokens' => $response->json('usageMetadata.candidatesTokenCount'),
            'response_time_ms' => $responseTimeMs,
        ];
    }

    /**
     * 会話の最初のやり取りから短いタイトルを生成する(ベストエフォート、失敗時は null)。
     */
    public function generateTitle(string $firstUserMessage, string $firstAssistantReply): ?string
    {
        try {
            $result = $this->ask(
                systemPrompt: 'あなたは会話のタイトルを生成するアシスタントです。'
                    .'以下のやり取りの内容を最もよく表す短いタイトルを日本語で 1 つだけ、'
                    .'20 文字以内・記号や引用符なしのプレーンテキストで出力してください。説明や前置きは不要です。',
                history: [],
                userMessage: "受講生の質問:\n{$firstUserMessage}\n\nAI の回答:\n{$firstAssistantReply}",
            );
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $title = trim($result['content']);
        $title = trim($title, "「」\"'` \n\r\t");

        return $title === '' ? null : mb_substr($title, 0, 100);
    }
}
