<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Enrollment;
use App\Services\MeetingAvailabilityService;
use Carbon\Carbon;

/**
 * 予約画面が呼ぶ空き枠取得ユースケース。JSON レスポンス用に整形した配列を返す。
 */
final class FetchAvailabilityAction
{
    public function __construct(private readonly MeetingAvailabilityService $availabilityService) {}

    /**
     * @return array{date: string, slots: list<array{slot_start: string, slot_end: string, available_coach_count: int}>}
     */
    public function __invoke(Enrollment $enrollment, Carbon $date): array
    {
        $slots = $this->availabilityService->slotsForCertification(
            $enrollment->loadMissing('certification')->certification,
            $date,
        );

        return [
            'date' => $date->toDateString(),
            'slots' => $slots->map(fn (array $slot) => [
                'slot_start' => $slot['slot_start']->toIso8601String(),
                'slot_end' => $slot['slot_end']->toIso8601String(),
                'available_coach_count' => $slot['available_coach_count'],
            ])->all(),
        ];
    }
}
