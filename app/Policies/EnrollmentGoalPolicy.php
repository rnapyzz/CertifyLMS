<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;

/**
 * 個人学習目標の認可ルール。CRUD・達成マーク・達成解除のすべてが「受講登録の本人(受講生)」専用。
 * コーチ・管理者・他受講生は一切操作できない(閲覧は受講登録詳細画面自体の EnrollmentPolicy::view に委ねる。
 * 目標一覧は個別の view ability を持たず、受講登録を閲覧できる者にはそのまま見える)。
 */
class EnrollmentGoalPolicy
{
    public function create(User $user, Enrollment $enrollment): bool
    {
        return $user->role === UserRole::Student && $enrollment->user_id === $user->id;
    }

    public function update(User $user, EnrollmentGoal $goal): bool
    {
        $goal->loadMissing('enrollment');

        return $user->role === UserRole::Student && $goal->enrollment->user_id === $user->id;
    }

    public function delete(User $user, EnrollmentGoal $goal): bool
    {
        return $this->update($user, $goal);
    }

    public function markAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $this->update($user, $goal);
    }

    public function unmarkAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $this->update($user, $goal);
    }
}
