<?php

declare(strict_types=1);

use App\Http\Controllers\Api\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| JSON API のルート定義。Sanctum SPA Cookie 認証(同一オリジン、GET /sanctum/csrf-cookie は
| パッケージが自動登録)で保護する。Web 画面側のルート(routes/web.php)とは独立した経路として維持する。
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// ============================================================
// TopBar 通知ポップオーバー向け JSON API(認証済ユーザーの本人宛の通知のみ)
// ============================================================
// 管理者はロールで弾かない(403 ではなく 200 + 0 件を返す)。管理者は既存の
// NotificationEligibilityService により通知を一切受信しないため、$user->notifications() が
// 常に空集合を返す結果として自然に「0 件」になる(要件確認済み)。
Route::middleware(['auth:sanctum'])->prefix('v1')->name('v1.')->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
});
