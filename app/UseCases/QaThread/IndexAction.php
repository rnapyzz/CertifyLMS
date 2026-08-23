<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Models\QaThread;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 質問掲示板の一覧取得ユースケース。admin / student / coach 共通(閲覧範囲は QaThread::scopeVisibleTo() で絞る)。
 *
 * `user` / `certification` を eager load + `replies` を withCount することで、スレッド件数が増えても
 * N+1 が発生しないようにする(一覧カードは投稿者名・資格名・回答数のみを表示すれば足りるため回答本体は取得しない)。
 */
final class IndexAction
{
    /**
     * @param array{certification_id?: ?string, status?: ?string, keyword?: ?string} $filters
     */
    public function __invoke(User $viewer, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return QaThread::query()
            ->visibleTo($viewer)
            ->filter($filters)
            ->with(['user', 'certification'])
            ->withCount('replies')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
