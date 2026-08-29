<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use Database\Factories\AiChatMessageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AI 相談 1 会話内の個別メッセージ(受講生の発言、または AI の応答)。
 *
 * user_id は会話所有者を denormalize した列(常に conversation.user_id と同じ)。日次送信回数の
 * 上限チェック(role=User の本日分をカウント)を conversation との JOIN 無しで行うために持つ。
 *
 * content は空文字をデフォルトとし NULL にしない(Blade 側の `$message->content === ''` 判定を壊さないため)。
 *
 * 関連: AiChatConversation / User(denormalized owner)
 */
class AiChatMessage extends Model
{
    /** @use HasFactory<AiChatMessageFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'status',
        'content',
        'error_detail',
        'model',
        'input_tokens',
        'output_tokens',
        'response_time_ms',
    ];

    protected $casts = [
        'role' => AiChatMessageRole::class,
        'status' => AiChatMessageStatus::class,
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'response_time_ms' => 'integer',
    ];

    /**
     * @return BelongsTo<AiChatConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiChatConversation::class, 'conversation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 受講生本人が本日(Asia/Tokyo)送信したメッセージ件数。日次送信上限のチェックに使う。
     * AI 応答の成否は問わない(送信自体が成功していればカウントする)。
     */
    public static function dailyCountForUser(User $user): int
    {
        return self::query()
            ->where('user_id', $user->id)
            ->where('role', AiChatMessageRole::User->value)
            ->whereDate('created_at', now()->toDateString())
            ->count();
    }

    /**
     * `resources/js/ai-chat/message-renderer.js` が期待する JSON 形状に整形する。
     *
     * @return array{id: string, role: string, content: string, status: string, model: ?string, response_time_ms: ?int, output_tokens: ?int, created_at: ?string}
     */
    public function toChatApiArray(): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role->value,
            'content' => $this->content,
            'status' => $this->status->value,
            'model' => $this->model,
            'response_time_ms' => $this->response_time_ms,
            'output_tokens' => $this->output_tokens,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
