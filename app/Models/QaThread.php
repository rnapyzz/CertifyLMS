<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use Database\Factories\QaThreadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 質問掲示板のスレッド(質問本体)を表す Model。投稿は受講生のみ、解決状態は `resolved_at` の有無で判定する。
 *
 * scope: visibleTo(User)(公開範囲: student = 公開済資格全件 / coach = 担当かつ公開済資格 / admin = 全件)
 *        / filter(array)(certification_id / status / keyword) / unresolved()
 */
class QaThread extends Model
{
    /** @use HasFactory<QaThreadFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'certification_id',
        'title',
        'body',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Certification, $this>
     */
    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    /**
     * @return HasMany<QaReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(QaReply::class);
    }

    public function isResolved(): bool
    {
        return ! is_null($this->resolved_at);
    }

    /**
     * `resolved_at` の有無から算出する解決状態(DB カラムは持たない computed attribute)。
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn (): QaThreadStatus => $this->resolved_at !== null
                ? QaThreadStatus::Resolved
                : QaThreadStatus::Unresolved,
        );
    }

    /**
     * 操作者ロールに応じて閲覧可能なスレッドへ絞り込む scope。
     * admin = 全件(公開停止中資格含む) / coach = 担当かつ公開済資格のみ / student = 公開済資格のみ。
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        return match ($viewer->role) {
            UserRole::Admin => $query,
            UserRole::Coach => $query
                ->whereIn('certification_id', $viewer->coachingCertificationIds())
                ->whereHas('certification', fn (Builder $q) => $q->published()),
            UserRole::Student => $query->whereHas('certification', fn (Builder $q) => $q->published()),
        };
    }

    /**
     * 一覧の絞り込み条件(資格 / 解決状態 / キーワード部分一致)を適用する scope。
     *
     * @param array{certification_id?: ?string, status?: ?string, keyword?: ?string} $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (! empty($filters['certification_id'])) {
            $query->where('certification_id', $filters['certification_id']);
        }

        if (($filters['status'] ?? '') === QaThreadStatus::Resolved->value) {
            $query->whereNotNull('resolved_at');
        } elseif (($filters['status'] ?? '') === QaThreadStatus::Unresolved->value) {
            $query->whereNull('resolved_at');
        }

        if (! empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function (Builder $q) use ($keyword): void {
                $q->where('title', 'LIKE', "%{$keyword}%")
                    ->orWhere('body', 'LIKE', "%{$keyword}%");
            });
        }

        return $query;
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }
}
