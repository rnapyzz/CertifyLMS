<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * AI 相談メッセージの状態。
 *
 * Pending は非同期(応答待ち)UI のための予約値で、本実装(同期応答のみ)では DB に永続化されない。
 * 受講生の発言(role=User)は常に Completed(送信=即成功)。
 */
enum AiChatMessageStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Error = 'error';
}
