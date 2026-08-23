<?php

declare(strict_types=1);

namespace App\UseCases\Dashboard;

use App\Enums\EnrollmentStatus;
use App\Enums\MeetingStatus;
use App\Http\Controllers\DashboardController;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\LearningSession;
use App\Models\Meeting;
use App\Models\QaThread;
use App\Models\User;
use App\Services\ChatUnreadCountService;
use App\UseCases\Dashboard\ViewModels\CoachDashboardViewModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * コーチダッシュボードの ViewModel を組み立てる Action。
 *
 * 担当資格に紐付く Enrollment 一覧(certification.coaches 経由) + 今日 / 明日の面談予約 +
 * 未読 chat 件数 + 未読 chat ルーム上位 5 件 + 未回答 Q&A 件数 + 直近 Q&A 上位 5 件 を集約する。
 *
 * 担当受講生一覧は表示専用(ソートなし)。表示行(氏名 / 担当資格名)は `with(['user', 'certification'])`
 * で eager load し、最終活動日時(担当受講生ごとの最終学習セッション開始時刻)は 1 件ずつ
 * `learningSessions()->max()` を呼ばず、LearningSession を enrollment_id で GROUP BY した集約クエリ
 * 1 本で取得する(担当受講生数に比例した N+1 を避けるため)。
 * 弱点カテゴリ集約 / 受講生メモ表示 / 滞留検知は本ロールでは表示しない(個別画面で対応)。
 *
 * @see DashboardController::index()
 */
final class FetchCoachDashboardAction
{
    use HasDashboardSafeFetch;

    public function __construct(
        private readonly ChatUnreadCountService $chatUnread,
    ) {}

    public function __invoke(User $coach): CoachDashboardViewModel
    {
        $coachingCertificationIds = $coach->coachingCertificationIds();

        $assignedEnrollments = Enrollment::query()
            ->whereIn('certification_id', $coachingCertificationIds)
            ->whereIn('status', [EnrollmentStatus::Learning, EnrollmentStatus::Passed])
            ->with(['user', 'certification'])
            ->get();

        $lastActivityByEnrollmentId = $assignedEnrollments->isEmpty()
            ? collect()
            : LearningSession::query()
                ->whereIn('enrollment_id', $assignedEnrollments->pluck('id'))
                ->groupBy('enrollment_id')
                ->selectRaw('enrollment_id, MAX(started_at) as last_started_at')
                ->pluck('last_started_at', 'enrollment_id');

        foreach ($assignedEnrollments as $enrollment) {
            $enrollment->last_activity_at = $lastActivityByEnrollmentId->get($enrollment->id);
        }

        $todayAndTomorrowMeetings = Meeting::query()
            ->where('coach_id', $coach->id)
            ->where('status', MeetingStatus::Reserved)
            ->whereBetween('scheduled_at', [now()->startOfDay(), now()->endOfDay()->addDay()])
            ->with(['student', 'enrollment.certification'])
            ->orderBy('scheduled_at')
            ->get();

        return new CoachDashboardViewModel(
            assignedEnrollments: $assignedEnrollments,
            todayAndTomorrowMeetings: $todayAndTomorrowMeetings,
            unreadChatCount: $this->safe(fn () => $this->chatUnread->roomCountForUser($coach)),
            recentUnreadChatRooms: $this->safe(fn () => $this->fetchRecentUnreadChatRooms($coach)),
            unansweredQaCount: $this->safe(fn () => $this->fetchUnansweredQaCount($coachingCertificationIds)),
            recentQaThreads: $this->safe(fn () => $this->fetchRecentUnansweredQaThreads($coachingCertificationIds)),
        );
    }

    /**
     * コーチ宛て未読 chat ルームの上位 5 件を返す。
     * 未読件数 0 のルームは除外、未読件数で並べ替えはせず最終メッセージ時刻順とする(`scopeOrderByLastMessage`)。
     *
     * @return Collection<int, ChatRoom>
     */
    private function fetchRecentUnreadChatRooms(User $coach): Collection
    {
        $rooms = ChatRoom::query()
            ->forUser($coach)
            ->with(['enrollment.user', 'enrollment.certification', 'latestMessage'])
            ->orderByLastMessage()
            ->get();

        return $rooms
            ->filter(fn (ChatRoom $room) => $this->chatUnread->messageCountInRoom($room, $coach) > 0)
            ->take(5)
            ->values();
    }

    /**
     * @param array<int, string> $certificationIds
     */
    private function fetchUnansweredQaCount(array $certificationIds): int
    {
        // 質問掲示板ルートが未登録の環境では集計しない（機能未提供時の防御、件数 0）
        if (! Route::has('qa-board.index')) {
            return 0;
        }

        return QaThread::query()
            ->whereIn('certification_id', $certificationIds)
            ->unresolved()
            ->whereDoesntHave('replies')
            ->count();
    }

    /**
     * 担当資格スコープの未回答 Q&A スレッド上位 5 件を新着順で返す。
     *
     * @param array<int, string> $certificationIds
     *
     * @return Collection<int, QaThread>
     */
    private function fetchRecentUnansweredQaThreads(array $certificationIds): Collection
    {
        // 質問掲示板ルートが未登録の環境では空一覧を返す（機能未提供時の防御）
        if (! Route::has('qa-board.index')) {
            return collect();
        }

        return QaThread::query()
            ->whereIn('certification_id', $certificationIds)
            ->unresolved()
            ->whereDoesntHave('replies')
            ->with(['user', 'certification'])
            ->latest()
            ->limit(5)
            ->get()
            ->values();
    }
}
