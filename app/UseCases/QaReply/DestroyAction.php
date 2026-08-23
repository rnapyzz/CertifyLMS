<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use Illuminate\Support\Facades\DB;

/**
 * 回答の物理削除ユースケース。投稿者本人 / admin(モデレーション)の両方から呼ばれる。
 */
final class DestroyAction
{
    public function __invoke(QaReply $reply): void
    {
        DB::transaction(function () use ($reply): void {
            $reply->delete();
        });
    }
}
