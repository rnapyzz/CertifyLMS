<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Mentoring\GoogleCalendarSyncException;
use App\Models\GoogleCredential;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendarApi;
use Google\Service\Calendar\Event as GoogleEvent;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\FreeBusyRequest;
use Google\Service\Calendar\FreeBusyRequestItem;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use Throwable;

/**
 * Google Calendar API との通信を集約する Service。
 *
 * 「Google との通信に失敗しても面談機能の根幹は止まらない」という要件を満たすため、
 * 空き時間取得(freeBusyIntervals) / 予定作成(createEvent) / 予定削除(deleteEvent) /
 * 連携解除時の失効(revoke)は、失敗時に自身で catch + report() し、呼出元へ例外を伝播させない
 * (フォールバック境界を Service に一本化し、Controller / Listener 側は try/catch 不要にする)。
 * 一方 OAuth 認可フロー(buildAuthorizationUrl / exchangeCode)は「連携」というユーザー操作の
 * 成否そのものなので、失敗時は例外をそのまま投げて Controller 側でエラー表示に変換する。
 *
 * `final` 不採用: 実際の Google API 通信を行う Service のため、テストでは
 * `Mockery::mock(GoogleCalendarService::class)` で差し替えて振る舞いを固定する
 * (`App\Services\UserStatusChangeService` と同じ理由)。
 */
class GoogleCalendarService
{
    private const SCOPE = 'https://www.googleapis.com/auth/calendar';

    /** アクセストークンの有効期限までの猶予(秒)。この秒数以内に切れる場合は事前に更新する。 */
    private const TOKEN_REFRESH_BUFFER_SECONDS = 60;

    /**
     * テストで Guzzle MockHandler 等の HTTP トランスポートを差し込むための任意の注入口。
     * null(デフォルト)の場合は Google\Client 標準のトランスポートを使用するため、
     * 本番 / 既存の呼び出し元(DI コンテナ経由の自動解決)の挙動は一切変わらない。
     */
    public function __construct(private readonly ?GuzzleClientInterface $httpClient = null) {}

    public function buildAuthorizationUrl(string $state, string $redirectUri): string
    {
        $client = $this->newClient($redirectUri);
        $client->setScopes([self::SCOPE]);
        $client->setAccessType('offline');
        // 再連携時も必ず refresh_token を受け取るため、同意画面を毎回強制する。
        $client->setPrompt('consent');
        $client->setState($state);

        return $client->createAuthUrl();
    }

    /**
     * 認可コードをアクセストークン / リフレッシュトークンに交換する。
     *
     * @return array{access_token: string, refresh_token?: string, expires_in: int}
     *
     * @throws GoogleCalendarSyncException
     */
    public function exchangeCode(string $code, string $redirectUri): array
    {
        $client = $this->newClient($redirectUri);

        try {
            $token = $client->fetchAccessTokenWithAuthCode($code);
        } catch (Throwable $e) {
            throw new GoogleCalendarSyncException('Google との認可コード交換に失敗しました。', previous: $e);
        }

        if (isset($token['error'])) {
            throw new GoogleCalendarSyncException((string) ($token['error_description'] ?? $token['error']));
        }

        return $token;
    }

    /**
     * 連携解除時に Google 側のトークンを失効させる(ベストエフォート、失敗しても連携解除自体は続行させる)。
     */
    public function revoke(GoogleCredential $credential): void
    {
        try {
            $client = $this->newClient();
            $client->revokeToken($credential->refresh_token ?: $credential->access_token);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * 指定コーチが Google カレンダー上で予定を持つ時間帯を返す。未連携 / API 失敗時は空配列(フォールバック)。
     *
     * @return list<array{start: Carbon, end: Carbon}>
     */
    public function busyIntervals(User $coach, Carbon $timeMin, Carbon $timeMax): array
    {
        $credential = $coach->googleCredential;
        if ($credential === null) {
            return [];
        }

        try {
            $credential = $this->ensureFreshToken($credential);
            $service = $this->serviceFor($credential);

            $request = new FreeBusyRequest;
            $request->setTimeMin($timeMin->toRfc3339String());
            $request->setTimeMax($timeMax->toRfc3339String());
            $item = new FreeBusyRequestItem;
            $item->setId($credential->calendar_id);
            $request->setItems([$item]);

            $response = $service->freebusy->query($request);
            $calendars = $response->getCalendars();
            $busy = $calendars[$credential->calendar_id]->getBusy() ?? [];

            return array_map(
                fn ($period) => [
                    'start' => Carbon::parse($period->getStart()),
                    'end' => Carbon::parse($period->getEnd()),
                ],
                $busy,
            );
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * 面談予定をコーチの Google カレンダーへ登録する。未連携 / API 失敗時は null(フォールバック)。
     */
    public function createEvent(Meeting $meeting): ?string
    {
        $meeting->loadMissing('coach', 'student', 'enrollment.certification');
        $coach = $meeting->coach;
        $credential = $coach?->googleCredential;
        if ($coach === null || $credential === null) {
            return null;
        }

        try {
            $credential = $this->ensureFreshToken($credential);
            $service = $this->serviceFor($credential);

            $start = $meeting->scheduled_at->copy();
            $end = $start->copy()->addHour();
            $certificationName = $meeting->enrollment?->certification?->name ?? '';

            $event = new GoogleEvent;
            $event->setSummary("面談: {$meeting->student?->name} 様({$certificationName})");
            $event->setDescription("Certify LMS 経由で予約された面談です。\nトピック: {$meeting->topic}");
            if ($coach->meeting_url !== null) {
                $event->setLocation($coach->meeting_url);
            }
            $event->setStart($this->eventDateTime($start));
            $event->setEnd($this->eventDateTime($end));

            $created = $service->events->insert($credential->calendar_id, $event);

            return $created->getId();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * 登録済の面談予定を Google カレンダーから削除する(ベストエフォート)。
     */
    public function deleteEvent(User $coach, string $eventId): void
    {
        $credential = $coach->googleCredential;
        if ($credential === null) {
            return;
        }

        try {
            $credential = $this->ensureFreshToken($credential);
            $service = $this->serviceFor($credential);
            $service->events->delete($credential->calendar_id, $eventId);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function eventDateTime(Carbon $at): EventDateTime
    {
        $dt = new EventDateTime;
        $dt->setDateTime($at->toRfc3339String());
        $dt->setTimeZone($at->getTimezone()->getName());

        return $dt;
    }

    /**
     * アクセストークンが期限切れ(または期限間近)であれば refresh_token で更新し、DB に永続化する。
     *
     * @throws GoogleCalendarSyncException
     */
    private function ensureFreshToken(GoogleCredential $credential): GoogleCredential
    {
        if ($credential->token_expires_at->subSeconds(self::TOKEN_REFRESH_BUFFER_SECONDS)->isFuture()) {
            return $credential;
        }

        $client = $this->newClient();

        try {
            $token = $client->fetchAccessTokenWithRefreshToken($credential->refresh_token);
        } catch (Throwable $e) {
            throw new GoogleCalendarSyncException('Google アクセストークンの更新に失敗しました。', previous: $e);
        }

        if (isset($token['error'])) {
            throw new GoogleCalendarSyncException((string) ($token['error_description'] ?? $token['error']));
        }

        $credential->forceFill([
            'access_token' => $token['access_token'],
            'token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
        ])->save();

        return $credential;
    }

    private function serviceFor(GoogleCredential $credential): GoogleCalendarApi
    {
        $client = $this->newClient();
        $client->setAccessToken([
            'access_token' => $credential->access_token,
        ]);

        return new GoogleCalendarApi($client);
    }

    private function newClient(?string $redirectUri = null): GoogleClient
    {
        $client = new GoogleClient;
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri($redirectUri ?? (string) config('services.google.redirect_uri'));

        if ($this->httpClient !== null) {
            $client->setHttpClient($this->httpClient);
        }

        return $client;
    }
}
