<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\NotificationType;
use App\Models\ChatRoom;
use App\Models\Meeting;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use App\Notifications\MeetingCanceledNotification;
use App\Notifications\MeetingReservedNotification;
use App\Notifications\QaReplyReceivedNotification;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 開発用 通知(database チャネル)シーダー。
 *
 * **設計思想(Seeder 業界標準: 状態網羅 + 固定アカウント)**:
 *
 * 1. 固定の受講生 / コーチ(`student@certify-lms.test` / `coach@certify-lms.test`)に、既読・未読を
 *    混在させた通知を投入する(一覧 / ページネーション / 全件既読の動作を確認するため)。
 * 2. 種別(chat_message_received / qa_reply_received / meeting_reserved / meeting_canceled)を混在させ、
 *    行クリック時の遷移先(chat / qa-board / meetings)がそれぞれ正しく動くことを確認できるようにする。
 * 3. `$user->notify()` は使わずテーブルへ直接 INSERT する(実メール送信・イベント再発火を避けるため)。
 *
 * 依存順序: `UserSeeder` → `ChatSeeder` / `QaBoardSeeder` / `MentoringSeeder` の後に走る前提
 * (通知本文が参照する実データが必要なため)。
 */
final class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()->where('email', 'student@certify-lms.test')->first();
        $coach = User::query()->where('email', 'coach@certify-lms.test')->first();

        if ($student === null || $coach === null) {
            $this->command?->warn('NotificationSeeder: 固定アカウントが存在しません。先に UserSeeder を実行してください。');

            return;
        }

        $this->seedForStudent($student, $coach);
        $this->seedForCoach($coach, $student);
    }

    private function seedForStudent(User $student, User $coach): void
    {
        $rows = [];

        $resolvedThread = QaThread::query()
            ->where('user_id', $student->id)
            ->whereNotNull('resolved_at')
            ->with('replies')
            ->first();

        if ($resolvedThread !== null && ($reply = $resolvedThread->replies->first()) !== null) {
            $rows[] = $this->row(
                type: NotificationType::QaReplyReceived,
                title: "「{$resolvedThread->title}」に回答が届きました",
                message: $this->excerpt($reply->body),
                url: route('qa-board.show', $resolvedThread->id),
                readAt: now()->subDays(6),
                createdAt: now()->subDays(7),
            );
        }

        $room = ChatRoom::query()
            ->whereHas('enrollment', fn ($q) => $q->where('user_id', $student->id))
            ->first();

        if ($room !== null) {
            $rows[] = $this->row(
                type: NotificationType::ChatMessageReceived,
                title: "{$coach->name} さんからメッセージが届きました",
                message: '直近の演習結果を見ると、2 分探索木の比較回数が苦手そうですね。',
                url: route('chat.show', $room->id),
                readAt: null,
                createdAt: now()->subHours(3),
            );
        }

        $reservedMeeting = Meeting::query()->where('student_id', $student->id)->where('status', 'reserved')->first();
        if ($reservedMeeting !== null) {
            $rows[] = $this->row(
                type: NotificationType::MeetingReserved,
                title: "{$coach->name} さんとの面談が確定しました",
                message: '日時: '.$reservedMeeting->scheduled_at->format('Y/m/d H:i'),
                url: route('meetings.show', $reservedMeeting->id),
                readAt: now()->subDays(1),
                createdAt: now()->subDays(2),
            );
        }

        $completedMeeting = Meeting::query()->where('student_id', $student->id)->where('status', 'completed')->first();
        if ($completedMeeting !== null) {
            $rows[] = $this->row(
                type: NotificationType::MeetingCanceled,
                title: "{$coach->name} さんが面談をキャンセルしました",
                message: '予定日時: '.$completedMeeting->scheduled_at->format('Y/m/d H:i'),
                url: route('meetings.show', $completedMeeting->id),
                readAt: null,
                createdAt: now()->subDays(14),
            );
        }

        foreach ($rows as $row) {
            $student->notifications()->create($row);
        }
    }

    private function seedForCoach(User $coach, User $student): void
    {
        $rows = [];

        $unresolvedThread = QaThread::query()
            ->whereHas('certification.coaches', fn ($q) => $q->where('users.id', $coach->id))
            ->whereNull('resolved_at')
            ->doesntHave('replies')
            ->first();

        if ($unresolvedThread !== null) {
            $rows[] = $this->row(
                type: NotificationType::QaReplyReceived,
                title: '担当資格の質問掲示板に未回答の質問があります',
                message: $this->excerpt($unresolvedThread->body),
                url: route('qa-board.show', $unresolvedThread->id),
                readAt: now()->subHours(20),
                createdAt: now()->subDays(1),
            );
        }

        $room = ChatRoom::query()
            ->whereHas('members', fn ($q) => $q->where('user_id', $coach->id))
            ->first();

        if ($room !== null) {
            $rows[] = $this->row(
                type: NotificationType::ChatMessageReceived,
                title: "{$student->name} さんからメッセージが届きました",
                message: 'ありがとうございます。例題を解いてから過去問に進む流れで進めてみます。',
                readAt: null,
                url: route('chat.show', $room->id),
                createdAt: now()->subHours(1),
            );
        }

        $reservedMeeting = Meeting::query()->where('coach_id', $coach->id)->where('status', 'reserved')->first();
        if ($reservedMeeting !== null) {
            $rows[] = $this->row(
                type: NotificationType::MeetingReserved,
                title: "{$student->name} さんから面談予約が入りました",
                message: '日時: '.$reservedMeeting->scheduled_at->format('Y/m/d H:i'),
                url: route('meetings.show', $reservedMeeting->id),
                readAt: null,
                createdAt: now()->subDays(2),
            );
        }

        foreach ($rows as $row) {
            $coach->notifications()->create($row);
        }
    }

    /**
     * DatabaseNotification::$casts が `data` を array cast しているため、ここでは json_encode せず
     * 素の配列を渡す(json_encode してしまうと二重エンコードされ `data` が文字列になってしまう)。
     *
     * @return array{id: string, type: string, data: array<string, string>, read_at: ?Carbon, created_at: Carbon, updated_at: Carbon}
     */
    private function row(
        NotificationType $type,
        string $title,
        string $message,
        string $url,
        ?Carbon $readAt,
        Carbon $createdAt,
    ): array {
        return [
            'id' => (string) Str::uuid(),
            'type' => match ($type) {
                NotificationType::QaReplyReceived => QaReplyReceivedNotification::class,
                NotificationType::ChatMessageReceived => ChatMessageReceivedNotification::class,
                NotificationType::MeetingReserved => MeetingReservedNotification::class,
                NotificationType::MeetingCanceled => MeetingCanceledNotification::class,
            },
            'data' => [
                'notification_type' => $type->value,
                'title' => $title,
                'message' => $message,
                'url' => $url,
            ],
            'read_at' => $readAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    private function excerpt(string $text): string
    {
        return mb_strimwidth(preg_replace('/\s+/', ' ', $text) ?? '', 0, 80, '…');
    }
}
