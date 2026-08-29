<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * AI 相談メッセージの発言者。
 */
enum AiChatMessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
}
