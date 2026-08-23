<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 質問掲示板のスレッドの新規作成ユースケース。
 */
final class StoreAction
{
    /**
     * @param array{certification_id: string, title: string, body: string} $validated QaThread/StoreRequest::rules() で検証済
     */
    public function __invoke(User $user, array $validated): QaThread
    {
        return DB::transaction(function () use ($user, $validated) {
            return $user->qaThreads()->create($validated);
        });
    }
}
