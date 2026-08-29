<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use RuntimeException;
use Throwable;

/**
 * Gemini API への問い合わせが失敗したことを表す例外。
 *
 * `GoogleCalendarService` の busyIntervals 等とは異なり、失敗を Service 内で握りつぶさない
 * (`App\Services\GeminiChatService::ask`)。呼出元の `SendMessageAction` がこれを catch して
 * AiChatMessage(status=Error, error_detail=upstreamStatus)として永続化しつつ、JSON 経由の
 * 応答では HTTP 502 + `{upstream_status}` に変換する必要があり、失敗理由(ステータスコード)を
 * 呼出元に伝える必要があるため。
 */
final class GeminiChatException extends RuntimeException
{
    public function __construct(string $message, private readonly ?int $upstreamStatus = null, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }

    public function upstreamStatus(): ?int
    {
        return $this->upstreamStatus;
    }
}
