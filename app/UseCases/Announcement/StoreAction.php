<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Listeners\Concerns\HasSafeNotificationDispatch;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 管理者お知らせの新規配信ユースケース。配信は不可逆(再配信 / 編集 / 取消なし)のため、
 * 対象確定 → Announcement 作成(配信件数 / 配信日時を確定) → 通知送信 を 1 リクエストで完結させる。
 *
 * 対象は常に「role=student かつ status=in_progress」に絞る(`App\Services\NotificationEligibilityService`
 * と同じ判定基準。招待中 / 卒業 / 退会済のユーザーは配信対象に含まれない)。
 *
 * 通知送信は `Announcement` の DB コミット後に行う(`App\UseCases\QaReply\StoreAction` と同様、
 * 副作用をトランザクション境界の外に出す設計。イベントではなく直接 `notify()` するため
 * `DB::afterCommit()` で登録する)。受信者ごとに個別 `notify()` し、1 人の配信失敗が
 * 残りの受信者への送信を巻き込まないようにする(`App\Listeners\SendChatMessageNotification` と同じ理由)。
 * 各 `notify()` は `AdminAnnouncementNotification::ShouldQueue` によりキューへ積むだけで即座に返るため、
 * 対象受講生が多い一斉配信でも発火元リクエストはブロックされない(T-A-05)。
 */
final class StoreAction
{
    use HasSafeNotificationDispatch;

    /**
     * @param array{title: string, body: string, target_type: string, target_certification_id?: ?string, target_user_id?: ?string} $validated
     */
    public function __invoke(User $admin, array $validated): Announcement
    {
        $targetType = AnnouncementTargetType::from($validated['target_type']);
        $recipients = $this->resolveRecipients($targetType, $validated);

        return DB::transaction(function () use ($admin, $validated, $targetType, $recipients) {
            $announcement = Announcement::create([
                'title' => $validated['title'],
                'body' => $validated['body'],
                'target_type' => $targetType,
                'target_certification_id' => $targetType === AnnouncementTargetType::Certification
                    ? $validated['target_certification_id']
                    : null,
                'target_user_id' => $targetType === AnnouncementTargetType::User
                    ? $validated['target_user_id']
                    : null,
                'created_by_user_id' => $admin->id,
                'dispatched_count' => $recipients->count(),
                'dispatched_at' => now(),
            ]);

            DB::afterCommit(function () use ($announcement, $recipients) {
                foreach ($recipients as $recipient) {
                    $this->safeNotify(fn () => $recipient->notify(new AdminAnnouncementNotification($announcement)));
                }
            });

            return $announcement;
        });
    }

    /**
     * @param array{target_certification_id?: ?string, target_user_id?: ?string} $validated
     *
     * @return Collection<int, User>
     */
    private function resolveRecipients(AnnouncementTargetType $targetType, array $validated): Collection
    {
        $query = match ($targetType) {
            AnnouncementTargetType::AllStudents => User::query(),
            AnnouncementTargetType::Certification => User::query()
                ->whereHas('enrollments', fn ($q) => $q->where('certification_id', $validated['target_certification_id'])),
            AnnouncementTargetType::User => User::query()->where('id', $validated['target_user_id']),
        };

        return $query->where('role', UserRole::Student)
            ->where('status', UserStatus::InProgress)
            ->get();
    }
}
