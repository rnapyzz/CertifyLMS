<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Events\QaReplyPosted;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 受講生 / コーチによる回答の新規投稿ユースケース。
 * 投稿後に `QaReplyPosted` を発火し、スレッド投稿者へ通知(アプリ内 + メール)する
 * (`App\Listeners\SendQaReplyNotification`)。
 */
final class StoreAction
{
    /**
     * @param array{body: string} $validated QaReply/StoreRequest::rules() で検証済
     */
    public function __invoke(User $user, QaThread $thread, array $validated): QaReply
    {
        $reply = DB::transaction(function () use ($user, $thread, $validated) {
            return $thread->replies()->create([
                ...$validated,
                'user_id' => $user->id,
            ]);
        });

        event(new QaReplyPosted($reply));

        return $reply;
    }
}
