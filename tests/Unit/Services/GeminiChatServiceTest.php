<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\AiChat\GeminiChatException;
use App\Services\GeminiChatService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Gemini API (generateContent) との通信を検証する。実際の外部通信は行わず Http::fake で固定する。
 *
 * @group external-api
 */
class GeminiChatServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.api_key' => 'test-api-key',
            'ai-chat.gemini.model' => 'gemini-2.5-flash',
        ]);
    }

    public function test_ask_returns_content_and_usage_metadata_on_success(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'こんにちは、質問にお答えします。']]]],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 42,
                    'candidatesTokenCount' => 18,
                ],
            ], 200),
        ]);

        $service = new GeminiChatService;
        $result = $service->ask('system prompt', [], '質問です');

        $this->assertSame('こんにちは、質問にお答えします。', $result['content']);
        $this->assertSame('gemini-2.5-flash', $result['model']);
        $this->assertSame(42, $result['input_tokens']);
        $this->assertSame(18, $result['output_tokens']);
        $this->assertIsInt($result['response_time_ms']);
    }

    public function test_ask_sends_system_instruction_and_history(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
            ], 200),
        ]);

        $service = new GeminiChatService;
        $service->ask('system prompt here', [
            ['role' => 'user', 'content' => '前の質問'],
            ['role' => 'model', 'content' => '前の回答'],
        ], '新しい質問');

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['systemInstruction']['parts'][0]['text'] === 'system prompt here'
                && count($body['contents']) === 3
                && $body['contents'][0]['role'] === 'user'
                && $body['contents'][0]['parts'][0]['text'] === '前の質問'
                && $body['contents'][1]['role'] === 'model'
                && $body['contents'][2]['parts'][0]['text'] === '新しい質問';
        });
    }

    public function test_ask_throws_with_upstream_status_on_http_error(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'rate limited'], 429),
        ]);

        $service = new GeminiChatService;

        try {
            $service->ask('system', [], 'question');
            $this->fail('GeminiChatException was not thrown.');
        } catch (GeminiChatException $e) {
            $this->assertSame(429, $e->upstreamStatus());
        }
    }

    public function test_ask_throws_without_calling_api_when_key_is_missing(): void
    {
        config(['services.gemini.api_key' => '']);
        Http::fake();

        $service = new GeminiChatService;

        try {
            $service->ask('system', [], 'question');
            $this->fail('GeminiChatException was not thrown.');
        } catch (GeminiChatException $e) {
            $this->assertNull($e->upstreamStatus());
        }

        Http::assertNothingSent();
    }

    public function test_generate_title_returns_null_on_failure_instead_of_throwing(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'server error'], 500),
        ]);

        $service = new GeminiChatService;

        $this->assertNull($service->generateTitle('質問', '回答'));
    }

    public function test_generate_title_returns_trimmed_text_on_success(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '「二分探索木の基礎」']]]]],
            ], 200),
        ]);

        $service = new GeminiChatService;

        $this->assertSame('二分探索木の基礎', $service->generateTitle('質問', '回答'));
    }

    public function test_ask_throws_when_upstream_returns_200_with_empty_response_body(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '']]]]],
            ], 200),
        ]);

        $service = new GeminiChatService;

        try {
            $service->ask('system', [], 'question');
            $this->fail('GeminiChatException was not thrown.');
        } catch (GeminiChatException $e) {
            $this->assertSame(200, $e->upstreamStatus());
        }
    }

    public function test_ask_throws_when_upstream_response_has_no_candidates_at_all(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'promptFeedback' => ['blockReason' => 'SAFETY'],
            ], 200),
        ]);

        $service = new GeminiChatService;

        $this->expectException(GeminiChatException::class);

        $service->ask('system', [], 'question');
    }

    public function test_ask_throws_gemini_chat_exception_on_network_connection_error(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => fn () => throw new ConnectionException('Connection refused.'),
        ]);

        $service = new GeminiChatService;

        try {
            $service->ask('system', [], 'question');
            $this->fail('GeminiChatException was not thrown.');
        } catch (GeminiChatException $e) {
            $this->assertNull($e->upstreamStatus());
        }
    }

    public function test_ask_succeeds_on_a_fresh_call_after_a_prior_call_failed(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['error' => 'server error'], 500)
                ->push([
                    'candidates' => [['content' => ['parts' => [['text' => '再送後の回答です。']]]]],
                ], 200),
        ]);
        $service = new GeminiChatService;

        try {
            $service->ask('system', [], '1回目の質問');
            $this->fail('GeminiChatException was not thrown.');
        } catch (GeminiChatException) {
            // 想定通りの失敗。Service 自体は失敗状態を保持しないため、次の呼び出しは独立して成功しうる。
        }

        $result = $service->ask('system', [], '2回目の質問');

        $this->assertSame('再送後の回答です。', $result['content']);
    }
}
