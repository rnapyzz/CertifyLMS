<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use Illuminate\Support\Facades\DB;

/**
 * 投稿者本人による回答編集ユースケース。
 */
final class UpdateAction
{
    /**
     * @param array{body: string} $validated QaReply/UpdateRequest::rules() で検証済
     */
    public function __invoke(QaReply $reply, array $validated): QaReply
    {
        return DB::transaction(function () use ($reply, $validated) {
            $reply->update($validated);

            return $reply->fresh();
        });
    }
}
