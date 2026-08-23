<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 面談リマインダーの配信タイミング。値はコマンドの `--window` オプション値と一致させる。
 */
enum MeetingReminderWindow: string
{
    case Eve = 'eve';
    case OneHourBefore = 'one_hour_before';
}
