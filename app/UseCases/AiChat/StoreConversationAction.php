<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;

/**
 * AI 相談会話を新規作成する、またはウィジェット起点で同一 section の既存会話があれば再利用する。
 *
 * - source=widget かつ section_id あり: 同一受講生 × 同一 section の直近会話があれば再利用する
 *   (「同じ教材の会話が乱立しないように」)。無ければ新規作成。
 * - source=full-screen: 常に新規作成(section_id を持たないため再利用判定は行わない)。
 *
 * section から資格(enrollment)を自動解決してタグ付けする: 受講生が読んでいる教材の資格に
 * 実際に受講登録していれば enrollment_id を設定し、ヘッダのバッジ表示(context-badges.blade.php)
 * や AI へのプロンプトに使う。受講登録が無ければ enrollment_id は null のまま
 * (全般相談と同じ「defaultEnrollment へのフォールバック表示」に任せる)。
 */
final class StoreConversationAction
{
    /**
     * @param array{source: string, section_id?: string|null, message?: string|null} $validated
     *
     * @return array{conversation: AiChatConversation, created: bool}
     */
    public function __invoke(User $user, array $validated): array
    {
        $sectionId = $validated['section_id'] ?? null;

        if ($validated['source'] === 'widget' && $sectionId !== null) {
            $existing = AiChatConversation::query()
                ->where('user_id', $user->id)
                ->where('section_id', $sectionId)
                ->orderByDesc('last_message_at')
                ->orderByDesc('created_at')
                ->first();

            if ($existing !== null) {
                return ['conversation' => $existing, 'created' => false];
            }
        }

        $section = $sectionId !== null ? Section::find($sectionId) : null;
        $enrollmentId = null;

        if ($section !== null) {
            $section->loadMissing('chapter.part');
            $certificationId = $section->chapter?->part?->certification_id;

            if ($certificationId !== null) {
                $enrollmentId = Enrollment::query()
                    ->where('user_id', $user->id)
                    ->where('certification_id', $certificationId)
                    ->value('id');
            }
        }

        $conversation = AiChatConversation::create([
            'user_id' => $user->id,
            'enrollment_id' => $enrollmentId,
            'section_id' => $section?->id,
            'title' => $section?->title ?? '新しい相談',
            'auto_title_enabled' => true,
        ]);

        return ['conversation' => $conversation, 'created' => true];
    }
}
