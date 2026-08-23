<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners\Concerns;

use App\Listeners\Concerns\HasSafeNotificationDispatch;
use RuntimeException;
use Tests\TestCase;

/**
 * HasSafeNotificationDispatch::safeNotify() が例外を握りつぶし、呼出元(Listener → event() →
 * 業務アクション)を巻き込んで 500 化させないことを検証する。
 * メール配信のキュー非同期化はスコープ外のため、通知は同期送信される前提(SMTP 到達不可等の
 * 一時的な配信失敗が本体の業務処理を止めてはならない)。
 */
class HasSafeNotificationDispatchTest extends TestCase
{
    public function test_safe_notify_swallows_exceptions(): void
    {
        $subject = new class
        {
            use HasSafeNotificationDispatch;

            public bool $ran = false;

            public function trigger(): void
            {
                $this->safeNotify(function (): void {
                    $this->ran = true;

                    throw new RuntimeException('SMTP connection failed');
                });
            }
        };

        $subject->trigger();

        $this->assertTrue($subject->ran, 'クロージャ自体は実行されているはず');
    }

    public function test_safe_notify_runs_closure_normally_when_no_exception(): void
    {
        $subject = new class
        {
            use HasSafeNotificationDispatch;

            public int $calls = 0;

            public function trigger(): void
            {
                $this->safeNotify(function (): void {
                    $this->calls++;
                });
            }
        };

        $subject->trigger();

        $this->assertSame(1, $subject->calls);
    }
}
