<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * アイコン画像のアップロードユースケース。
 *
 * `avatars/{ulid}.{ext}` 形式で public disk に保存し、`users.avatar_url` を `/storage/avatars/{ulid}.{ext}` に
 * 更新する(教材内画像 `SectionImage\StoreAction` と同じ保存規約)。DB 更新のコミット後に旧ファイルを
 * 削除し、DB 更新が失敗した場合は新規保存したファイルを削除して orphan ファイルを残さない。
 */
final class UploadAvatarAction
{
    public function __invoke(User $user, UploadedFile $file): User
    {
        $ulid = (string) Str::ulid();
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $path = "avatars/{$ulid}.{$ext}";
        $previousPath = $this->storedPath($user->avatar_url);

        try {
            return DB::transaction(function () use ($user, $file, $path, $previousPath, $ulid, $ext) {
                Storage::disk('public')->putFileAs('avatars', $file, "{$ulid}.{$ext}");

                $user->update(['avatar_url' => '/storage/'.$path]);

                DB::afterCommit(function () use ($previousPath): void {
                    if ($previousPath !== null) {
                        Storage::disk('public')->delete($previousPath);
                    }
                });

                return $user->fresh();
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }
    }

    private function storedPath(?string $avatarUrl): ?string
    {
        if ($avatarUrl === null || ! str_starts_with($avatarUrl, '/storage/')) {
            return null;
        }

        return substr($avatarUrl, strlen('/storage/'));
    }
}
