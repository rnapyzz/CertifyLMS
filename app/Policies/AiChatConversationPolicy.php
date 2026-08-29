<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AiChatConversation;
use App\Models\User;

/**
 * AI 相談会話(AiChatConversation)の認可ルール。
 *
 * 完全に受講生本人専結のプライベートな相談記録のため、所有者本人以外は閲覧含め一切許可しない
 * (コーチ・管理者にも共有しない、`EnrollmentNote` のような業務記録とは性質が異なる)。
 */
class AiChatConversationPolicy
{
    public function view(User $user, AiChatConversation $conversation): bool
    {
        return $conversation->user_id === $user->id;
    }

    public function update(User $user, AiChatConversation $conversation): bool
    {
        return $conversation->user_id === $user->id;
    }

    public function delete(User $user, AiChatConversation $conversation): bool
    {
        return $conversation->user_id === $user->id;
    }
}
