<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * アイコン画像の削除ユースケース。`users.avatar_url` を null に戻し、アイコン未設定の表示(イニシャル)に戻す。
 */
final class RemoveAvatarAction
{
    public function __invoke(User $user): User
    {
        return DB::transaction(function () use ($user) {
            $previousPath = $this->storedPath($user->avatar_url);

            $user->update(['avatar_url' => null]);

            DB::afterCommit(function () use ($previousPath): void {
                if ($previousPath !== null) {
                    Storage::disk('public')->delete($previousPath);
                }
            });

            return $user->fresh();
        });
    }

    private function storedPath(?string $avatarUrl): ?string
    {
        if ($avatarUrl === null || ! str_starts_with($avatarUrl, '/storage/')) {
            return null;
        }

        return substr($avatarUrl, strlen('/storage/'));
    }
}
