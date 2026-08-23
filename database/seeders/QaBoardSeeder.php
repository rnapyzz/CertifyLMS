<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * 開発用 質問掲示板(qa-board) シーダー。
 *
 * **設計思想(Seeder 業界標準: 状態網羅 + 固定アカウント)**:
 *
 * 1. **公開済資格ごとに未解決 / 解決済を混在させる**: 一覧の資格 / 解決状態フィルタ・並び順を実機確認できるようにする。
 * 2. **回答数(0 件 / 数件)と作成日時をばらつかせる**: 「未回答」バッジ表示・ページネーション・削除動作(未回答スレッドのみ
 *    削除可能)を実機確認できるようにする。
 * 3. **固定 student(`student@certify-lms.test`)を投稿者にしたスレッドを用意**: 未解決 1 件 + 解決済(コーチ回答付き)1 件を
 *    用意し、「自分の質問」動線・解決マーク操作を安定して確認できるようにする。
 *
 * 依存順序: `UserSeeder` → `CertificationSeeder`(担当コーチ割当含む) → 本 Seeder。
 */
final class QaBoardSeeder extends Seeder
{
    public function run(): void
    {
        $certifications = Certification::query()->published()->with('coaches')->get();

        if ($certifications->isEmpty()) {
            $this->command?->warn('QaBoardSeeder: 公開済の資格が存在しません。先に CertificationSeeder を実行してください。');

            return;
        }

        $students = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->where('email', '!=', 'student@certify-lms.test')
            ->get();

        if ($students->isEmpty()) {
            $this->command?->warn('QaBoardSeeder: 受講中の受講生が存在しません。先に UserSeeder を実行してください。');

            return;
        }

        foreach ($certifications as $certification) {
            $this->seedThreadsForCertification($certification, $students);
        }

        $this->seedFixedStudentThreads($certifications, $students);
    }

    /**
     * @param Collection<int, User> $students
     */
    private function seedThreadsForCertification(Certification $certification, Collection $students): void
    {
        // 未解決 3 件(未回答 / 回答 1 件 / 回答 2 件)+ 解決済 2 件を目安に、著者・作成日時をばらつかせる。
        $plan = [
            ['resolved' => false, 'replyCount' => 0, 'daysAgo' => 1],
            ['resolved' => false, 'replyCount' => 1, 'daysAgo' => 4],
            ['resolved' => false, 'replyCount' => 2, 'daysAgo' => 7],
            ['resolved' => true, 'replyCount' => 1, 'daysAgo' => 12],
            ['resolved' => true, 'replyCount' => 3, 'daysAgo' => 20],
        ];

        foreach ($plan as $i => $row) {
            /** @var User $author */
            $author = $students->random();
            $createdAt = now()->subDays($row['daysAgo'])->subHours($i);

            $thread = $this->createThread(
                certification: $certification,
                author: $author,
                createdAt: $createdAt,
                resolved: $row['resolved'],
                resolvedAt: $row['resolved'] ? $createdAt->copy()->addDay() : null,
            );

            $this->seedReplies($thread, $certification, $students, $row['replyCount'], $createdAt);
        }
    }

    /**
     * @param Collection<int, Certification> $certifications
     * @param Collection<int, User> $students
     */
    private function seedFixedStudentThreads(Collection $certifications, Collection $students): void
    {
        $fixedStudent = User::query()->where('email', 'student@certify-lms.test')->first();
        if ($fixedStudent === null || $certifications->count() < 2) {
            return;
        }

        // 1 件目: 未解決(回答なし) — 「未回答」バッジ・解決マーク導線の確認用。
        $unresolvedCert = $certifications->get(0);
        $this->createThread(
            certification: $unresolvedCert,
            author: $fixedStudent,
            createdAt: now()->subDays(2),
            resolved: false,
            resolvedAt: null,
            title: $unresolvedCert->name.'の演習問題で解法が分かりません',
            body: "章末の演習問題を解いていますが、想定される解法と自分の考え方がずれている気がします。\nどこで詰まっているのか整理できていないので、着眼点だけでもヒントをいただけますでしょうか。",
        );

        // 2 件目: 解決済(コーチの回答付き) — 「自分の質問」一覧・解決済表示の確認用。
        $resolvedCert = $certifications->get(1);
        $createdAt = now()->subDays(8);
        $thread = $this->createThread(
            certification: $resolvedCert,
            author: $fixedStudent,
            createdAt: $createdAt,
            resolved: true,
            resolvedAt: $createdAt->copy()->addDay(),
            title: $resolvedCert->name.'の学習の進め方について相談です',
            body: "独学で進めていますが、どの分野から手をつけるべきか悩んでいます。\nおすすめの順番があれば教えてください。",
        );

        $coach = $resolvedCert->coaches->first() ?? $students->random();
        $repliedAt = $createdAt->copy()->addHours(5);
        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $coach->id,
            'body' => 'まずは頻出分野から着手し、過去問で出題傾向を掴むのがおすすめです。基礎が固まってきたら苦手分野を重点的に復習しましょう。',
        ]);
        $reply->forceFill(['created_at' => $repliedAt, 'updated_at' => $repliedAt])->save();
    }

    private function createThread(
        Certification $certification,
        User $author,
        Carbon $createdAt,
        bool $resolved,
        ?Carbon $resolvedAt,
        ?string $title = null,
        ?string $body = null,
    ): QaThread {
        $thread = QaThread::factory()->create([
            'certification_id' => $certification->id,
            'user_id' => $author->id,
            'title' => $title ?? fake()->sentence(8),
            'body' => $body ?? fake()->realText(300),
            'resolved_at' => $resolved ? $resolvedAt : null,
        ]);

        $thread->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $thread;
    }

    /**
     * @param Collection<int, User> $students
     */
    private function seedReplies(QaThread $thread, Certification $certification, Collection $students, int $count, Carbon $threadCreatedAt): void
    {
        if ($count === 0) {
            return;
        }

        $repliers = $certification->coaches
            ->concat($students->reject(fn (User $s) => $s->id === $thread->user_id))
            ->shuffle();

        if ($repliers->isEmpty()) {
            return;
        }

        for ($i = 0; $i < $count; $i++) {
            $replier = $repliers[$i % $repliers->count()];
            $repliedAt = $threadCreatedAt->copy()->addHours(($i + 1) * 3);

            $reply = QaReply::factory()->create([
                'qa_thread_id' => $thread->id,
                'user_id' => $replier->id,
                'body' => fake()->realText(200),
            ]);
            $reply->forceFill(['created_at' => $repliedAt, 'updated_at' => $repliedAt])->save();
        }
    }
}
