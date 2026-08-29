<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Stripe サーバからの Webhook 通知。ブラウザセッションを持たないため CSRF トークンが無く、
        // 正当性の担保は署名検証(StripeWebhookController)のみで行う。
        'webhooks/stripe',
    ];
}
