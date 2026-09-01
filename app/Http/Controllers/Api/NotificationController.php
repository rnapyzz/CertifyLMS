<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * TopBar 通知ポップオーバー向けの通知 JSON API(`auth:sanctum` で保護、本人宛の通知のみ)。
 *
 * 一覧はポップオーバー内ページネーションを持たない仕様(スコープ外)のため、直近 {@see self::LIST_LIMIT} 件のみ返す。
 * `unread_count` は常に受講生本人の未読総数(取得件数に関わらない実数)を返し、
 * TopBar バッジとポップオーバーの「未読」タブ件数の両方をこの値で同期させる想定。
 *
 * 管理者をロールで弾く実装はしない。管理者は `NotificationEligibilityService` により通知を
 * 一切受信しない設計のため、`index()` は自然に空配列 + `unread_count: 0` を返す(403 ではなく
 * 200 + 0 件が正しい挙動、要件確認済み)。
 *
 * 認可は `App\Policies\DatabaseNotificationPolicy::view`(本人宛のみ)に委譲し、
 * 既存の Web 版 `App\Http\Controllers\NotificationController` と同じ認可規則を踏襲する。
 */
class NotificationController extends Controller
{
    private const LIST_LIMIT = 20;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->limit(self::LIST_LIMIT)
            ->get();

        return response()->json([
            'notifications' => $notifications->map(fn (DatabaseNotification $n) => $this->format($n))->all(),
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, DatabaseNotification $notification): JsonResponse
    {
        $this->authorize('view', $notification);

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['unread_count' => 0]);
    }

    /**
     * @return array{id: string, title: string, message: string, target_url: string, created_at_human: string, unread: bool}
     */
    private function format(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => $notification->id,
            'title' => $data['title'] ?? '通知',
            'message' => $data['message'] ?? ($data['body_preview'] ?? ''),
            'target_url' => $data['url'] ?? route('notifications.show', $notification),
            'created_at_human' => $notification->created_at?->diffForHumans() ?? '',
            'unread' => $notification->read_at === null,
        ];
    }
}
