<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * 受講生 1 人あたりの 1 日の送信回数上限(`config('ai-chat.daily_message_limit')`)に達した場合の例外。
 *
 * JSON 経由(`messages.store`)は Laravel 標準の 429 + `{message}` のまま返す(chat-client.js は
 * 429 のボディを読まない設計のため、Handler での特別な整形は不要)。HTML 経由
 * (`conversations.store` の初回メッセージ同期応答)は Controller 側で catch し、
 * redirect back + flash error に変換する。
 */
final class AiChatDailyLimitExceededException extends TooManyRequestsHttpException
{
    public function __construct()
    {
        parent::__construct(message: '本日の質問送信回数の上限に達しました。日付が変わってから再度お試しください。');
    }
}
