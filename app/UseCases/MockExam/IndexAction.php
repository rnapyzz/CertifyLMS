<?php

declare(strict_types=1);

namespace App\UseCases\MockExam;

use App\Enums\UserRole;
use App\Models\MockExam;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * admin / coach 用の模試マスタ一覧をフィルタ付きで取得するユースケース。
 *
 * - admin: 全資格配下の MockExam
 * - coach: 担当資格(certification.coaches)配下の MockExam のみ
 * フィルタ: keyword(部分一致) / certification_id / is_published
 *
 * `resources/views/mock-exam/management/index.blade.php` が行ごとに参照する
 * 所属資格(certification) / 更新者(updatedBy) / 問題数(mockExamQuestions) を
 * Eager Loading + withCount で一括取得し、ページ内の模試件数分の N+1 を防ぐ
 * (作成者(createdBy) は本画面では表示しないため取得しない)。
 */
final class IndexAction
{
    public function __invoke(
        User $auth,
        ?string $keyword = null,
        ?string $certificationId = null,
        ?bool $isPublished = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = MockExam::query()
            ->with(['certification', 'updatedBy'])
            ->withCount('mockExamQuestions');

        if ($auth->role === UserRole::Coach) {
            $query->whereHas(
                'certification.coaches',
                fn ($q) => $q->where('users.id', $auth->id),
            );
        }

        if ($keyword !== null && $keyword !== '') {
            $query->where('title', 'LIKE', '%'.$keyword.'%');
        }

        if ($certificationId !== null && $certificationId !== '') {
            $query->where('certification_id', $certificationId);
        }

        if ($isPublished !== null) {
            $query->where('is_published', $isPublished);
        }

        return $query
            ->orderBy('certification_id')
            ->orderBy('order')
            ->orderByDesc('updated_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
