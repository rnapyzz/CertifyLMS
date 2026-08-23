<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\User;

/**
 * 管理者お知らせ配信の認可ルール。全 ability が admin 専用(ルート自体も `role:admin`
 * ミドルウェアで保護されているが、Policy でも明示する)。編集 / 削除 / 再配信の ability は
 * 仕様上存在しないため定義しない(配信は不可逆)。
 */
class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
