<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Fortify\UpdateUserPassword;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 本人によるパスワード変更。既存の Fortify Action(`UpdateUserPassword`、現在パスワードの照合 +
 * `updatePassword` エラーバッグでの検証を内包)をそのまま再利用する
 * (本アプリの `UseCases\{Entity}\{Action}Action` パターンとは別の Fortify 例外領域として扱う)。
 */
class PasswordController extends Controller
{
    public function update(Request $request, UpdateUserPassword $action): RedirectResponse
    {
        $action->update($request->user(), $request->only([
            'current_password',
            'password',
            'password_confirmation',
        ]));

        return redirect()
            ->route('settings.profile.edit', ['tab' => 'password'])
            ->with('success', 'パスワードを変更しました。');
    }
}
