<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\Profile\UpdateRequest;
use App\UseCases\Settings\UpdateProfileAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * プロフィール設定画面(氏名 / 自己紹介 / コーチのみ固定面談 URL)。全ロール共通、常に本人自身が対象。
 * パスワード変更タブは `PasswordController`、アイコン画像は `AvatarController` に分離する。
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.profile', [
            'user' => $request->user(),
        ]);
    }

    public function update(UpdateRequest $request, UpdateProfileAction $action): RedirectResponse
    {
        $action($request->user(), $request->validated());

        return redirect()
            ->route('settings.profile.edit')
            ->with('success', 'プロフィールを更新しました。');
    }
}
