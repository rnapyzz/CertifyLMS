<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\Avatar\StoreRequest;
use App\UseCases\Settings\RemoveAvatarAction;
use App\UseCases\Settings\UploadAvatarAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * アイコン画像のアップロード / 削除。全ロール共通、常に本人自身が対象。
 */
class AvatarController extends Controller
{
    public function store(StoreRequest $request, UploadAvatarAction $action): RedirectResponse
    {
        $action($request->user(), $request->file('avatar'));

        return redirect()
            ->route('settings.profile.edit')
            ->with('success', 'アイコン画像を更新しました。');
    }

    public function destroy(Request $request, RemoveAvatarAction $action): RedirectResponse
    {
        $action($request->user());

        return redirect()
            ->route('settings.profile.edit')
            ->with('success', 'アイコン画像を削除しました。');
    }
}
