<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ChatMessage;
use App\UseCases\Chat\StoreMessageAction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ChatRoom にメッセージが投稿された際に発火する(通知専用の)イベント。
 * リアルタイム UI 更新用の `ChatMessageSent`(Pusher broadcast)とは別の関心事として分離しており、
 * `App\Listeners\SendChatMessageNotification` が送信者以外のルーム参加者への通知発火を担う。
 *
 * @see StoreMessageAction
 */
final class ChatMessagePosted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly ChatMessage $message) {}
}
