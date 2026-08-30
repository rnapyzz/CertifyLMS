<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

/**
 * `Http::preventStrayRequests()` により、`Http::` facade 経由(Gemini 連携)の実通信は
 * テストで `Http::fake()` を用意し忘れた場合に例外で即座に検知される
 * (外部連携のテストが誤って実ネットワークへ到達しないことを保証する)。
 * Google Calendar / Stripe SDK はそれぞれ独自の HTTP トランスポートを持ち `Http::` facade を
 * 経由しないため、これらは各 Service 自体のテスト(MockHandler 注入・署名の純粋ローカル計算)
 * および呼出元テスト(Service を Mockery で丸ごと差し替え)側で個別に実通信を防いでいる。
 */
abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }
}
