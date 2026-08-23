<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;

/**
 * コーチメモ(EnrollmentNote)の認可ルール。
 *
 * - viewAny / create: 担当資格に登録した受講生の受講登録に対してのみコーチが操作可、管理者は任意の受講登録に操作可。
 *   受講生は(自分自身の受講登録であっても)常に拒否 — 業務記録の性質を保つため閲覧含め一切見せない。
 * - update / delete: 作成者本人(コーチ)、または管理者(任意のメモ)。担当資格から外れた後の自分のメモ編集は
 *   引き続き許可する(要件は「自分が作成したメモ」とのみ規定しており、現在の担当を条件にしていないため)。
 */
class EnrollmentNotePolicy
{
    public function viewAny(User $user, Enrollment $enrollment): bool
    {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Coach => $this->isAssignedCoach($enrollment, $user),
            default => false,
        };
    }

    public function create(User $user, Enrollment $enrollment): bool
    {
        return $this->viewAny($user, $enrollment);
    }

    public function update(User $user, EnrollmentNote $note): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->role === UserRole::Coach && $note->author_id === $user->id;
    }

    public function delete(User $user, EnrollmentNote $note): bool
    {
        return $this->update($user, $note);
    }

    private function isAssignedCoach(Enrollment $enrollment, User $coach): bool
    {
        $enrollment->loadMissing('certification.coaches');

        return $enrollment->certification?->coaches->contains('id', $coach->id) ?? false;
    }
}
