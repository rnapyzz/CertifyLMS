<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * 投稿者本人による解決済マークユースケース。
 */
final class ResolveAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        return DB::transaction(function () use ($thread) {
            $thread->update(['resolved_at' => now()]);

            return $thread->fresh();
        });
    }
}
