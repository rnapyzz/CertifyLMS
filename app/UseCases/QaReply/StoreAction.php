<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 受講生 / コーチによる回答の新規投稿ユースケース。
 */
final class StoreAction
{
    /**
     * @param array{body: string} $validated QaReply/StoreRequest::rules() で検証済
     */
    public function __invoke(User $user, QaThread $thread, array $validated): QaReply
    {
        return DB::transaction(function () use ($user, $thread, $validated) {
            return $thread->replies()->create([
                ...$validated,
                'user_id' => $user->id,
            ]);
        });
    }
}
