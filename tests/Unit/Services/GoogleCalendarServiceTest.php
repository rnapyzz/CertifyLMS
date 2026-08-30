<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\Mentoring\GoogleCalendarSyncException;
use App\Models\GoogleCredential;
use App\Models\Meeting;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GoogleCalendarService の低レベル HTTP 通信を Guzzle MockHandler で差し替えて検証する。
 *
 * `Google\Client::setHttpClient()` (SDK 自身が公開する拡張ポイント) へ MockHandler 付きの
 * Guzzle Client を注入することで、実ネットワーク通信を一切行わずに
 * 認可フロー(認可 URL 生成 / コード交換 / 連携解除)とカレンダー操作
 * (空き時刻取得 / 予定作成 / 予定削除)、およびトークン自動更新・失敗時フォールバックを検証する。
 *
 * @group external-api
 */
class GoogleCalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(array $responses): GoogleCalendarService
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new GuzzleClient(['handler' => $handlerStack, 'http_errors' => false]);

        return new GoogleCalendarService($guzzle);
    }

    private function jsonResponse(int $status, array $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }

    public function test_build_authorization_url_returns_google_oauth_url_without_any_http_call(): void
    {
        $service = $this->makeService([]);

        $url = $service->buildAuthorizationUrl('state-123', 'https://example.test/callback');

        $this->assertStringContainsString('accounts.google.com', $url);
        $this->assertStringContainsString('state=state-123', $url);
        $this->assertStringContainsString('prompt=consent', $url);
    }

    public function test_exchange_code_returns_token_on_success(): void
    {
        $service = $this->makeService([
            $this->jsonResponse(200, [
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
        ]);

        $token = $service->exchangeCode('auth-code', 'https://example.test/callback');

        $this->assertSame('new-access-token', $token['access_token']);
        $this->assertSame('new-refresh-token', $token['refresh_token']);
    }

    public function test_exchange_code_throws_sync_exception_when_google_returns_error_body(): void
    {
        $service = $this->makeService([
            $this->jsonResponse(400, [
                'error' => 'invalid_grant',
                'error_description' => 'Malformed auth code.',
            ]),
        ]);

        $this->expectException(GoogleCalendarSyncException::class);

        $service->exchangeCode('bad-code', 'https://example.test/callback');
    }

    public function test_revoke_does_not_throw_when_google_responds_successfully(): void
    {
        $credential = GoogleCredential::factory()->make();
        $service = $this->makeService([
            new Response(200),
        ]);

        $service->revoke($credential);

        $this->assertTrue(true);
    }

    public function test_revoke_swallows_failure_as_a_best_effort_operation(): void
    {
        $credential = GoogleCredential::factory()->make();
        $service = $this->makeService([
            new Response(500),
        ]);

        $service->revoke($credential);

        $this->assertTrue(true);
    }

    public function test_busy_intervals_returns_intervals_when_token_is_still_fresh(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCredential::factory()->for($coach)->create([
            'token_expires_at' => now()->addHour(),
        ]);

        $service = $this->makeService([
            $this->jsonResponse(200, [
                'kind' => 'calendar#freeBusy',
                'calendars' => [
                    'primary' => [
                        'busy' => [
                            ['start' => '2026-09-01T10:00:00+09:00', 'end' => '2026-09-01T11:00:00+09:00'],
                        ],
                    ],
                ],
            ]),
        ]);

        $intervals = $service->busyIntervals($coach, now(), now()->addWeek());

        $this->assertCount(1, $intervals);
        $this->assertInstanceOf(Carbon::class, $intervals[0]['start']);
        $this->assertSame('2026-09-01 10:00:00', $intervals[0]['start']->format('Y-m-d H:i:s'));
    }

    public function test_busy_intervals_refreshes_expired_token_before_querying(): void
    {
        $coach = User::factory()->coach()->create();
        $credential = GoogleCredential::factory()->for($coach)->create([
            'access_token' => 'stale-access-token',
            'token_expires_at' => now()->subMinute(),
        ]);

        $service = $this->makeService([
            $this->jsonResponse(200, [
                'access_token' => 'refreshed-access-token',
                'expires_in' => 3600,
            ]),
            $this->jsonResponse(200, [
                'kind' => 'calendar#freeBusy',
                'calendars' => ['primary' => ['busy' => []]],
            ]),
        ]);

        $intervals = $service->busyIntervals($coach, now(), now()->addWeek());

        $this->assertSame([], $intervals);
        $this->assertSame('refreshed-access-token', $credential->fresh()->access_token);
    }

    public function test_busy_intervals_returns_empty_array_when_token_refresh_fails(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCredential::factory()->for($coach)->create([
            'token_expires_at' => now()->subMinute(),
        ]);

        $service = $this->makeService([
            $this->jsonResponse(400, [
                'error' => 'invalid_grant',
                'error_description' => 'Token has been expired or revoked.',
            ]),
        ]);

        $intervals = $service->busyIntervals($coach, now(), now()->addWeek());

        $this->assertSame([], $intervals);
    }

    public function test_busy_intervals_returns_empty_array_when_calendar_api_call_fails(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCredential::factory()->for($coach)->create([
            'token_expires_at' => now()->addHour(),
        ]);

        $service = $this->makeService([
            $this->jsonResponse(500, ['error' => ['message' => 'internal error']]),
        ]);

        $intervals = $service->busyIntervals($coach, now(), now()->addWeek());

        $this->assertSame([], $intervals);
    }

    public function test_busy_intervals_returns_empty_array_when_coach_has_no_credential(): void
    {
        $coach = User::factory()->coach()->create();
        $service = $this->makeService([]);

        $intervals = $service->busyIntervals($coach, now(), now()->addWeek());

        $this->assertSame([], $intervals);
    }

    public function test_create_event_returns_event_id_on_success(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCredential::factory()->for($coach)->create([
            'token_expires_at' => now()->addHour(),
        ]);
        $meeting = Meeting::factory()->forCoach($coach)->create();

        $service = $this->makeService([
            $this->jsonResponse(200, [
                'kind' => 'calendar#event',
                'id' => 'evt_abc123',
                'status' => 'confirmed',
            ]),
        ]);

        $eventId = $service->createEvent($meeting);

        $this->assertSame('evt_abc123', $eventId);
    }

    public function test_create_event_returns_null_when_api_call_fails(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCredential::factory()->for($coach)->create([
            'token_expires_at' => now()->addHour(),
        ]);
        $meeting = Meeting::factory()->forCoach($coach)->create();

        $service = $this->makeService([
            $this->jsonResponse(500, ['error' => ['message' => 'internal error']]),
        ]);

        $eventId = $service->createEvent($meeting);

        $this->assertNull($eventId);
    }

    public function test_create_event_returns_null_when_coach_has_no_google_credential(): void
    {
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->forCoach($coach)->create();
        $service = $this->makeService([]);

        $eventId = $service->createEvent($meeting);

        $this->assertNull($eventId);
    }

    public function test_delete_event_succeeds_without_throwing(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCredential::factory()->for($coach)->create([
            'token_expires_at' => now()->addHour(),
        ]);

        $service = $this->makeService([
            new Response(204),
        ]);

        $service->deleteEvent($coach, 'evt_abc123');

        $this->assertTrue(true);
    }

    public function test_delete_event_gracefully_handles_an_already_deleted_event(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCredential::factory()->for($coach)->create([
            'token_expires_at' => now()->addHour(),
        ]);

        $service = $this->makeService([
            $this->jsonResponse(410, ['error' => ['message' => 'Resource has been deleted']]),
        ]);

        $service->deleteEvent($coach, 'evt-already-gone');

        $this->assertTrue(true);
    }

    public function test_delete_event_is_a_noop_when_coach_has_no_google_credential(): void
    {
        $coach = User::factory()->coach()->create();
        $service = $this->makeService([]);

        $service->deleteEvent($coach, 'evt_abc123');

        $this->assertTrue(true);
    }
}
