<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Meeting;

/**
 * 面談詳細表示に必要な関連を取得するユースケース。認可(Policy)は呼出元 Controller の責務。
 */
final class ShowAction
{
    public function __invoke(Meeting $meeting): Meeting
    {
        return $meeting->loadMissing([
            'enrollment.certification',
            'coach',
            'student',
            'canceledBy',
            'meetingMemo',
        ]);
    }
}
