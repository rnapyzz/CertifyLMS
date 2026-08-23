<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\QaThread;
use App\Models\User;

/**
 * QaThread(質問掲示板スレッド)リソースに対する認可ポリシー。
 *
 * - viewAny: admin / coach / student いずれも一覧自体は閲覧可(取得スコープは QaThread::scopeVisibleTo() で絞る)
 * - view: admin は全件(公開停止中資格含む)、student は公開済資格のみ、coach は担当かつ公開済資格のみ
 * - create: 受講生のみ(投稿は受講生専用)
 * - update / resolve / unresolve: 投稿者本人(受講生)のみ。resolve/unresolve は現在の状態と矛盾する遷移を拒否する
 * - delete: 投稿者本人(受講生、かつ回答が 1 件もない場合のみ) または admin(モデレーション、件数制限なし)。
 *   admin は内容編集 / 解決マークの代行はできない(update / resolve / unresolve は常に false)
 */
class QaThreadPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Coach, UserRole::Student], true);
    }

    public function view(User $user, QaThread $qaThread): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        $qaThread->loadMissing('certification.coaches');

        if ($qaThread->certification?->status !== CertificationStatus::Published) {
            return false;
        }

        return match ($user->role) {
            UserRole::Student => true,
            UserRole::Coach => $qaThread->certification->coaches->contains('id', $user->id),
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Student;
    }

    public function update(User $user, QaThread $qaThread): bool
    {
        return $user->role === UserRole::Student && $qaThread->user_id === $user->id;
    }

    public function delete(User $user, QaThread $qaThread): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->role === UserRole::Student
            && $qaThread->user_id === $user->id
            && $qaThread->replies()->doesntExist();
    }

    public function resolve(User $user, QaThread $qaThread): bool
    {
        return $user->role === UserRole::Student
            && $qaThread->user_id === $user->id
            && ! $qaThread->isResolved();
    }

    public function unresolve(User $user, QaThread $qaThread): bool
    {
        return $user->role === UserRole::Student
            && $qaThread->user_id === $user->id
            && $qaThread->isResolved();
    }
}
