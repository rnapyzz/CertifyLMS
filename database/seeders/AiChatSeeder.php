<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EnrollmentStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * 開発用 AI 相談(Gemini チャットボット)シーダー。
 *
 * 固定 student(student@certify-lms.test)に、状態を網羅する 3 件の会話を投入する:
 * 1. 全般相談(教材文脈なし、完了済のやり取り 2 往復)
 * 2. 教材文脈あり(受講中資格の Section に紐づく会話、完了済 1 往復)
 * 3. AI 応答エラー状態(受講生の発言は残るが、AI 応答が error のまま)
 *
 * 依存順序: `UserSeeder` → `EnrollmentSeeder` → `ContentSeeder` → 本 Seeder。
 */
final class AiChatSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()->where('email', 'student@certify-lms.test')->first();

        if ($student === null) {
            $this->command?->warn('AiChatSeeder: 固定 student が存在しません。先に UserSeeder を実行してください。');

            return;
        }

        $this->seedGeneralConversation($student);
        $this->seedSectionScopedConversation($student);
        $this->seedErroredConversation($student);
    }

    private function seedGeneralConversation(User $student): void
    {
        $conversation = AiChatConversation::create([
            'user_id' => $student->id,
            'enrollment_id' => null,
            'section_id' => null,
            'title' => '学習の進め方について',
            'auto_title_enabled' => false,
            'last_message_at' => now()->subDays(2),
        ]);

        $this->seedExchange(
            $conversation,
            $student,
            userText: '効率よく学習を進めるコツを教えてください。',
            assistantText: '毎日決まった時間に学習する習慣をつけること、演習問題で理解度を確認しながら進めることをおすすめします。苦手分野はドリル機能で重点的に復習しましょう。',
            at: now()->subDays(2)->subMinutes(10),
        );
        $this->seedExchange(
            $conversation,
            $student,
            userText: '模試の結果が伸び悩んでいます。どうすればいいですか?',
            assistantText: '分野別ヒートマップで弱点分野を特定し、苦手分野ドリルを優先的に解くのが効果的です。間違えた問題は解説を読み直して、同じ間違いを繰り返さないようにしましょう。',
            at: now()->subDays(2),
        );
    }

    private function seedSectionScopedConversation(User $student): void
    {
        $enrollment = $student->enrollments()->where('status', EnrollmentStatus::Learning->value)->first();
        $section = $enrollment !== null
            ? Section::query()->whereHas(
                'chapter.part',
                fn ($q) => $q->where('certification_id', $enrollment->certification_id),
            )->orderBy('order')->first()
            : null;

        if ($section === null) {
            return;
        }

        $conversation = AiChatConversation::create([
            'user_id' => $student->id,
            'enrollment_id' => $enrollment?->id,
            'section_id' => $section->id,
            'title' => $section->title,
            'auto_title_enabled' => true,
            'last_message_at' => now()->subHours(3),
        ]);

        $this->seedExchange(
            $conversation,
            $student,
            userText: 'このセクションの要点を3行でまとめてください。',
            assistantText: '要点は次の3つです。1) 基本的な考え方を理解すること。2) 具体例で手を動かして確認すること。3) 演習問題で定着させること。わからない箇所があれば遠慮なく聞いてください。',
            at: now()->subHours(3),
        );
    }

    private function seedErroredConversation(User $student): void
    {
        $conversation = AiChatConversation::create([
            'user_id' => $student->id,
            'enrollment_id' => null,
            'section_id' => null,
            'title' => '新しい相談',
            'auto_title_enabled' => true,
            'last_message_at' => now()->subMinutes(30),
        ]);

        AiChatMessage::factory()->for($conversation, 'conversation')->create([
            'user_id' => $student->id,
            'content' => '模擬試験の合格可能性スコアはどう計算されていますか?',
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]);

        AiChatMessage::factory()->errored()->for($conversation, 'conversation')->create([
            'user_id' => $student->id,
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]);
    }

    private function seedExchange(
        AiChatConversation $conversation,
        User $student,
        string $userText,
        string $assistantText,
        Carbon $at,
    ): void {
        AiChatMessage::factory()->for($conversation, 'conversation')->create([
            'user_id' => $student->id,
            'content' => $userText,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        AiChatMessage::factory()->fromAssistant()->for($conversation, 'conversation')->create([
            'user_id' => $student->id,
            'content' => $assistantText,
            'created_at' => $at->copy()->addSeconds(3),
            'updated_at' => $at->copy()->addSeconds(3),
        ]);
    }
}
