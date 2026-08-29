<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MeetingQuota\CreateCheckoutSessionRequest;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\UseCases\MeetingQuota\CreateCheckoutSessionAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Stripe\Exception\ExceptionInterface as StripeExceptionInterface;

/**
 * 追加面談パックの購入(Stripe Checkout への委譲)Controller。
 *
 * - select: 公開中パック一覧の選択画面
 * - create: 選択パックで Checkout Session を作成し、Stripe がホストする決済ページへ redirect
 * - success: 決済フローからの戻り先(実際の残数反映は Webhook 経由、本画面は表示のみ)
 */
class MeetingQuotaCheckoutController extends Controller
{
    public function select(): View
    {
        $plans = MeetingPack::query()->published()->ordered()->get();

        return view('meeting-quota.checkout-select', ['plans' => $plans]);
    }

    public function create(CreateCheckoutSessionRequest $request, CreateCheckoutSessionAction $action): RedirectResponse
    {
        $pack = MeetingPack::query()->published()->findOrFail($request->validated()['meeting_pack_id']);

        try {
            $url = $action(
                $request->user(),
                $pack,
                route('meeting-quota.checkout.success'),
                route('meeting-quota.checkout.select'),
            );
        } catch (StripeExceptionInterface $e) {
            report($e);

            return redirect()
                ->route('meeting-quota.checkout.select')
                ->with('error', '決済ページの作成に失敗しました。時間をおいて再度お試しください。');
        }

        return redirect()->away($url);
    }

    public function success(Request $request): View
    {
        $sessionId = $request->query('session_id');

        $payment = $sessionId !== null
            ? Payment::query()
                ->where('user_id', $request->user()->id)
                ->where('stripe_checkout_session_id', $sessionId)
                ->with('meetingPack')
                ->first()
            : null;

        return view('meeting-quota.success', ['payment' => $payment]);
    }
}
