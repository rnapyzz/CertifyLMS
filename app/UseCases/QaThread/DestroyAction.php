<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * スレッドの物理削除ユースケース。投稿者本人(未回答の場合のみ)/ admin(モデレーション)の両方から呼ばれる。
 * 配下の回答は FK cascadeOnDelete で同時に物理削除される(削除履歴は保持しない)。
 */
final class DestroyAction
{
    public function __invoke(QaThread $thread): void
    {
        DB::transaction(function () use ($thread): void {
            $thread->delete();
        });
    }
}
