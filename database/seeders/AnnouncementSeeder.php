<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AnnouncementTargetType;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 管理者お知らせ配信の開発用シーダー。
 *
 * 配信対象の 3 種類(全受講生 / 資格指定 / ユーザー指定)それぞれで 1 件ずつ Announcement を投入し、
 * 各お知らせに紐づく受講生の通知(database チャネル)も合わせて投入する
 * (配信履歴の一覧・詳細、および受講生側の通知一覧 / 詳細ページの実機確認用)。
 *
 * `NotificationSeeder` と同じ理由で `$user->notify()` は使わず、Announcement / notifications
 * テーブルへ直接 INSERT する(実メール送信・イベント再発火を避けるため)。
 *
 * 依存順序: `UserSeeder` → `CertificationSeeder` → `EnrollmentSeeder` の後に走る前提。
 */
final class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@certify-lms.test')->first();

        if ($admin === null) {
            $this->command?->warn('AnnouncementSeeder: 固定 admin アカウントが存在しません。先に UserSeeder を実行してください。');

            return;
        }

        $this->seedAllStudents($admin);
        $this->seedCertification($admin);
        $this->seedUser($admin);
    }

    private function seedAllStudents(User $admin): void
    {
        $recipients = $this->eligibleStudents();

        $announcement = Announcement::create([
            'title' => 'システムメンテナンスのお知らせ',
            'body' => "日頃より Certify LMS をご利用いただきありがとうございます。\n下記日程でシステムメンテナンスを実施するため、一時的にご利用いただけません。\n\n日時: 2026/09/01 02:00〜04:00\n\nご不便をおかけしますが、ご理解のほどよろしくお願いいたします。",
            'target_type' => AnnouncementTargetType::AllStudents,
            'target_certification_id' => null,
            'target_user_id' => null,
            'created_by_user_id' => $admin->id,
            'dispatched_count' => $recipients->count(),
            'dispatched_at' => now()->subDays(3),
        ]);
        $announcement->forceFill(['created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)])->save();

        $this->seedNotifications($announcement, $recipients, now()->subDays(3));
    }

    private function seedCertification(User $admin): void
    {
        $certification = Certification::query()->where('name', 'LIKE', '%TOEIC%')->first();

        if ($certification === null) {
            return;
        }

        $recipients = User::query()
            ->where('role', UserRole::Student)
            ->where('status', UserStatus::InProgress)
            ->whereHas('enrollments', fn ($q) => $q->where('certification_id', $certification->id))
            ->get();

        $announcement = Announcement::create([
            'title' => "「{$certification->name}」教材アップデートのお知らせ",
            'body' => "「{$certification->name}」の教材を一部更新しました。\n最新の出題傾向に合わせてパート3の演習問題を追加しています。\nマイページから最新の教材をご確認ください。",
            'target_type' => AnnouncementTargetType::Certification,
            'target_certification_id' => $certification->id,
            'target_user_id' => null,
            'created_by_user_id' => $admin->id,
            'dispatched_count' => $recipients->count(),
            'dispatched_at' => now()->subDays(2),
        ]);
        $announcement->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)])->save();

        $this->seedNotifications($announcement, $recipients, now()->subDays(2));
    }

    private function seedUser(User $admin): void
    {
        $student = User::query()->where('email', 'student@certify-lms.test')->first();

        if ($student === null) {
            return;
        }

        $recipients = new Collection([$student]);

        $announcement = Announcement::create([
            'title' => '学習状況についてのフォローアップ',
            'body' => "いつも学習お疲れ様です。\n直近の学習進捗を確認したところ、順調に進んでいらっしゃいますね。\nこの調子で引き続き頑張ってください。何かお困りのことがあれば chat 経由でいつでもご相談ください。",
            'target_type' => AnnouncementTargetType::User,
            'target_certification_id' => null,
            'target_user_id' => $student->id,
            'created_by_user_id' => $admin->id,
            'dispatched_count' => $recipients->count(),
            'dispatched_at' => now()->subDay(),
        ]);
        $announcement->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()])->save();

        $this->seedNotifications($announcement, $recipients, now()->subDay());
    }

    /**
     * @return Collection<int, User>
     */
    private function eligibleStudents(): Collection
    {
        return User::query()
            ->where('role', UserRole::Student)
            ->where('status', UserStatus::InProgress)
            ->get();
    }

    /**
     * @param Collection<int, User> $recipients
     */
    private function seedNotifications(Announcement $announcement, Collection $recipients, Carbon $createdAt): void
    {
        foreach ($recipients as $recipient) {
            $recipient->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => AdminAnnouncementNotification::class,
                'data' => [
                    'notification_type' => NotificationType::AdminAnnouncement->value,
                    'title' => $announcement->title,
                    'message' => mb_strimwidth(preg_replace('/\s+/', ' ', $announcement->body) ?? '', 0, 80, '…'),
                    'body' => $announcement->body,
                ],
                'read_at' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
