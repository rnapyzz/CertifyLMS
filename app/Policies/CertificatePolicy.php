<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\User;

/**
 * 修了証(Certificate)PDF ダウンロードの認可ルール。
 *
 * - 受講生: 本人が受領した修了証のみ(退会前 / 学習中でなくても可、修了証は永続資産のため
 *   `active-learning` ミドルウェアを route に付けない設計と対になっている)。
 * - コーチ: 修了証の資格(Certification)に現在アサインされている場合のみ。担当外資格は不可。
 * - 管理者: 全件可。
 */
class CertificatePolicy
{
    public function download(User $user, Certificate $certificate): bool
    {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Student => $certificate->user_id === $user->id,
            UserRole::Coach => $this->isAssignedCoach($certificate, $user),
        };
    }

    private function isAssignedCoach(Certificate $certificate, User $coach): bool
    {
        $certificate->loadMissing('certification.coaches');

        return $certificate->certification?->coaches->contains('id', $coach->id) ?? false;
    }
}
