<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;

/**
 * QaReply(質問掲示板の回答)リソースに対する認可ポリシー。
 *
 * - create: 受講生 / コーチ(担当かつ公開済資格のみ)。admin は回答できない
 * - update: 投稿者本人(受講生 / コーチ)のみ
 * - delete: 投稿者本人(受講生 / コーチ)、または admin(モデレーション)
 */
class QaReplyPolicy
{
    public function create(User $user, QaThread $qaThread): bool
    {
        if (! in_array($user->role, [UserRole::Student, UserRole::Coach], true)) {
            return false;
        }

        $qaThread->loadMissing('certification.coaches');

        if ($qaThread->certification?->status !== CertificationStatus::Published) {
            return false;
        }

        if ($user->role === UserRole::Coach) {
            return $qaThread->certification->coaches->contains('id', $user->id);
        }

        return true;
    }

    public function update(User $user, QaReply $qaReply): bool
    {
        return in_array($user->role, [UserRole::Student, UserRole::Coach], true)
            && $qaReply->user_id === $user->id;
    }

    public function delete(User $user, QaReply $qaReply): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return in_array($user->role, [UserRole::Student, UserRole::Coach], true)
            && $qaReply->user_id === $user->id;
    }
}
