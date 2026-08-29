<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\StripeCheckoutService;
use App\UseCases\MeetingQuota\HandleStripeWebhookAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

/**
 * Stripe からの Webhook 通知を受け取る公開エンドポイント(認証なし、署名検証のみが正当性の担保)。
 *
 * ルートは `auth` ミドルウェアを一切通さず、CSRF 検証も対象外(`App\Http\Middleware\VerifyCsrfToken::$except`)。
 * 署名検証に失敗した場合は 400 を返す(Stripe 側はこれを再送対象として扱う)。
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request, StripeCheckoutService $stripe, HandleStripeWebhookAction $action): Response
    {
        try {
            $event = $stripe->constructWebhookEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
            );
        } catch (UnexpectedValueException|SignatureVerificationException $e) {
            report($e);

            return response('invalid payload or signature', 400);
        }

        $action($event);

        return response('ok', 200);
    }
}
