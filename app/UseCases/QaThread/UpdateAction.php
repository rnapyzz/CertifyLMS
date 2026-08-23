<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * 投稿者本人によるスレッド編集ユースケース。資格(certification_id)は更新しない。
 */
final class UpdateAction
{
    /**
     * @param array{title: string, body: string} $validated QaThread/UpdateRequest::rules() で検証済
     */
    public function __invoke(QaThread $thread, array $validated): QaThread
    {
        return DB::transaction(function () use ($thread, $validated) {
            $thread->update($validated);

            return $thread->fresh();
        });
    }
}
