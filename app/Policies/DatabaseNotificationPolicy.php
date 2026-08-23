<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Laravel 標準の database 通知(`Illuminate\Notifications\DatabaseNotification`)に対する認可ポリシー。
 * 本人宛(`notifiable`)の通知のみ閲覧・既読化を許可する。
 */
class DatabaseNotificationPolicy
{
    public function view(User $user, DatabaseNotification $notification): bool
    {
        return $notification->notifiable_type === User::class
            && $notification->notifiable_id === $user->id;
    }
}
