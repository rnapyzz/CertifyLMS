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
// TopBar 通知ポップオーバー向け JSON API(受講生 / コーチのみ、本人宛の通知のみ)
// ============================================================
Route::middleware(['auth:sanctum', 'role:student,coach'])->prefix('v1')->name('v1.')->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
});
