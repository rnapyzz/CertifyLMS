<?php

declare(strict_types=1);

namespace App\Console\Commands\Mentoring;

use App\Enums\MeetingReminderWindow;
use App\UseCases\MeetingReminder\SendRemindersAction;
use Illuminate\Console\Command;

/**
 * 予約済み面談の当事者(受講生・コーチ)へ、前日 / 開始 1 時間前にリマインダー通知を配信する
 * Schedule Command。`--window` で配信タイミングを指定する(`App\Console\Kernel::schedule` で
 * eve / one_hour_before それぞれ別スケジュールとして登録する)。
 *
 * 重複起動・再実行への耐性は本 Command ではなく `SendRemindersAction`(クエリでの既送信除外 +
 * `meeting_reminders` の DB 一意制約)が担う。
 */
class SendMeetingRemindersCommand extends Command
{
    protected $signature = 'notifications:send-meeting-reminders {--window= : eve(前日) または one_hour_before(1時間前)}';

    protected $description = '予約済み面談の当事者へ前日 / 開始1時間前のリマインダー通知を配信する';

    public function handle(SendRemindersAction $action): int
    {
        $windowValue = $this->option('window');
        $window = is_string($windowValue) ? MeetingReminderWindow::tryFrom($windowValue) : null;

        if ($window === null) {
            $this->error('--window には eve または one_hour_before を指定してください。');

            return self::FAILURE;
        }

        $sent = $action($window);

        $this->info("面談リマインダー({$window->value})を {$sent} 件配信しました。");

        return self::SUCCESS;
    }
}
