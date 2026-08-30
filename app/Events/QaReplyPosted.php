<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\QaReply;
use App\UseCases\QaReply\StoreAction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 質問掲示板のスレッドに回答が投稿された際に発火するイベント。
 * `App\Listeners\SendQaReplyNotification` がスレッド投稿者への通知発火を担う。
 *
 * @see StoreAction
 */
final class QaReplyPosted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly QaReply $reply) {}
}
