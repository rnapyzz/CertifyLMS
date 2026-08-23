<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 本人によるプロフィール情報(氏名 / 自己紹介 / コーチのみ固定面談 URL)の更新ユースケース。
 * メールアドレス / ロール / アカウント状態はここでは更新しない(管理者経由のみ)。
 */
final class UpdateProfileAction
{
    /**
     * @param array{name: string, bio?: ?string, meeting_url?: ?string} $validated
     */
    public function __invoke(User $user, array $validated): User
    {
        return DB::transaction(function () use ($user, $validated) {
            $data = [
                'name' => $validated['name'],
                'bio' => $validated['bio'] ?? null,
            ];

            if ($user->role === UserRole::Coach) {
                $data['meeting_url'] = $validated['meeting_url'] ?? null;
            }

            $user->update($data);

            return $user->fresh();
        });
    }
}
