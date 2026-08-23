<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * 認証ユーザー共通の通知一覧・既読化 Controller(受講生・コーチ・admin 共通、admin は本 MVP では
 * 通知を受信しないため一覧は常に空になる)。
 *
 * - index: 自分宛の通知一覧(全件 / 未読のみタブ切替 + ページネーション)
 * - show: 遷移先business画面を持たない通知(将来の運営お知らせ等)の全文閲覧用フォールバック
 * - markAsRead: 本人宛の通知のみ既読化し、通知に紐づく業務画面へ redirect(未読化忘れを防ぐため 1 操作で完結させる)
 * - markAllAsRead: 自分宛の未読通知を一括既読化
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $tab = $request->query('tab') === 'unread' ? 'unread' : 'all';

        $notifications = ($tab === 'unread' ? $user->unreadNotifications() : $user->notifications())
            ->paginate(20)
            ->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications()->count(),
            'tab' => $tab,
        ]);
    }

    public function show(DatabaseNotification $notification): View
    {
        $this->authorize('view', $notification);

        return view('notifications.show', compact('notification'));
    }

    public function markAsRead(DatabaseNotification $notification): RedirectResponse
    {
        $this->authorize('view', $notification);

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        $url = is_array($notification->data) ? ($notification->data['url'] ?? null) : null;

        return redirect($url ?? route('notifications.show', $notification));
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return redirect()
            ->route('notifications.index')
            ->with('success', 'すべての通知を既読にしました。');
    }
}
