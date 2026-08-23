<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

/**
 * 通知の配信対象になり得るかを判定するサービス。
 *
 * - 管理者は本 MVP では通知を受信しない(お知らせ配信含め管理者向け通知は対象外)。
 * - 受講中(in_progress)以外(招待中 / 卒業 / 退会)のユーザーはプラン機能から外れているため対象外。
 *
 * 各業務イベントの Listener から通知送信直前に呼び出す想定。
 */
final class NotificationEligibilityService
{
    public function isEligible(User $user): bool
    {
        return $user->role !== UserRole::Admin
            && $user->status === UserStatus::InProgress;
    }
}
