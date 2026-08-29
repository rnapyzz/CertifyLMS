<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Models\AiChatConversation;

/**
 * 会話を物理削除する(メッセージは ai_chat_messages.conversation_id の cascadeOnDelete で連動削除)。
 */
final class DestroyConversationAction
{
    public function __invoke(AiChatConversation $conversation): void
    {
        $conversation->delete();
    }
}
