<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\User;

/**
 * 受講プランマスタの認可ルール。全 ability が admin 専用。
 * 受講生・コーチは一覧を含め一切アクセスできない(運営側マスタ管理画面のため)。
 *
 * delete の可否(下書き状態 かつ 受講者未紐づき)は本 Policy では判定せず `Plan\DestroyAction` に委譲する
 * (詳細画面は `@can('delete', $plan)` の下に常に削除ボタンを表示し、条件を満たさない場合は 409 で拒否する設計)。
 */
class PlanPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function view(User $auth, Plan $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function create(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function update(User $auth, Plan $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function delete(User $auth, Plan $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function publish(User $auth, Plan $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function archive(User $auth, Plan $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function unarchive(User $auth, Plan $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }
}
