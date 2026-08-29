<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Exceptions\Mentoring\GoogleCalendarSyncException;
use App\Http\Controllers\Controller;
use App\Models\GoogleCredential;
use App\Services\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * コーチ本人による Google カレンダー連携(OAuth 認可フロー)。`role:coach` ミドルウェアで他ロールは 403。
 *
 * `redirect` → Google 側の同意画面へ遷移 → `callback` → 連携完了、の 2 ステップ。
 * `state` パラメータをセッションに保存した乱数と突き合わせることで、連携処理が本人のブラウザセッション
 * から発生したものであることを検証する(CSRF / なりすまし対策)。
 */
class GoogleCalendarController extends Controller
{
    private const STATE_SESSION_KEY = 'google_calendar_oauth_state';

    private const REDIRECT_PATH_SESSION_KEY = 'google_calendar_oauth_redirect_path';

    public function redirect(Request $request, GoogleCalendarService $service): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put(self::STATE_SESSION_KEY, $state);

        $redirectPath = $request->query('redirect_path');
        if (is_string($redirectPath) && str_starts_with($redirectPath, '/') && ! str_starts_with($redirectPath, '//')) {
            $request->session()->put(self::REDIRECT_PATH_SESSION_KEY, $redirectPath);
        } else {
            $request->session()->forget(self::REDIRECT_PATH_SESSION_KEY);
        }

        $authorizationUrl = $service->buildAuthorizationUrl($state, route('settings.google-calendar.callback'));

        return redirect()->away($authorizationUrl);
    }

    public function callback(Request $request, GoogleCalendarService $service): RedirectResponse
    {
        $expectedState = $request->session()->pull(self::STATE_SESSION_KEY);
        $redirectPath = $request->session()->pull(self::REDIRECT_PATH_SESSION_KEY) ?? route('settings.availability.index');

        $state = $request->query('state');
        if (! is_string($state) || $expectedState === null || ! hash_equals($expectedState, $state)) {
            abort(403, '連携リクエストの検証に失敗しました。お手数ですがもう一度お試しください。');
        }

        if ($request->query('error') !== null) {
            return redirect($redirectPath)->with('error', 'Googleカレンダーとの連携がキャンセルされました。');
        }

        $code = $request->query('code');
        if (! is_string($code) || $code === '') {
            return redirect($redirectPath)->with('error', 'Googleカレンダーとの連携に失敗しました。');
        }

        try {
            $token = $service->exchangeCode($code, route('settings.google-calendar.callback'));
        } catch (GoogleCalendarSyncException $e) {
            report($e);

            return redirect($redirectPath)->with('error', 'Googleカレンダーとの連携に失敗しました。時間をおいて再度お試しください。');
        }

        $user = $request->user();
        $refreshToken = $token['refresh_token'] ?? $user->googleCredential?->refresh_token;

        if (! is_string($refreshToken) || $refreshToken === '') {
            report(new GoogleCalendarSyncException('Google からリフレッシュトークンを取得できませんでした。'));

            return redirect($redirectPath)->with('error', 'Googleカレンダーとの連携に失敗しました。時間をおいて再度お試しください。');
        }

        GoogleCredential::updateOrCreate(
            ['user_id' => $user->id],
            [
                'access_token' => $token['access_token'],
                'refresh_token' => $refreshToken,
                'token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
                'calendar_id' => 'primary',
                'connected_at' => now(),
            ],
        );

        return redirect($redirectPath)->with('success', 'Googleカレンダーと連携しました。');
    }

    public function destroy(Request $request, GoogleCalendarService $service): RedirectResponse
    {
        $credential = $request->user()->googleCredential;

        if ($credential !== null) {
            $service->revoke($credential);
            $credential->delete();
        }

        return redirect()
            ->route('settings.availability.index')
            ->with('success', 'Googleカレンダー連携を解除しました。');
    }
}
