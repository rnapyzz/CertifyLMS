<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Models\AiChatConversation;

/**
 * 会話タイトルの手動変更。以後 AI による自動上書きを止めるため auto_title_enabled を false にする。
 */
final class UpdateConversationAction
{
    public function __invoke(AiChatConversation $conversation, string $title): AiChatConversation
    {
        $conversation->forceFill([
            'title' => $title,
            'auto_title_enabled' => false,
        ])->save();

        return $conversation;
    }
}
