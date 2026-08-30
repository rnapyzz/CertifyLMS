<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use App\Services\MeetingQuotaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 受講生本人の面談一覧を取得するユースケース。filter(upcoming/past/all)でクエリを切り替える。
 */
final class IndexAction
{
    public function __construct(private readonly MeetingQuotaService $quotaService) {}

    /**
     * @return array{meetings: LengthAwarePaginator<int, Meeting>, filter: string, meetingsRemaining: int}
     */
    public function __invoke(User $student, string $filter): array
    {
        $query = Meeting::query()
            ->with(['enrollment.certification', 'coach'])
            ->forStudent($student)
            ->orderByDesc('scheduled_at');

        $meetings = match ($filter) {
            'past' => $query->past()->paginate(20),
            'all' => $query->paginate(20),
            default => $query->upcoming()->paginate(20),
        };

        return [
            'meetings' => $meetings,
            'filter' => $filter,
            'meetingsRemaining' => $this->quotaService->remaining($student),
        ];
    }
}
