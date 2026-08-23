<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MeetingReminderWindow;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 面談リマインダーの配信済みログ。`(meeting_id, window)` の DB 一意制約と組み合わせて、
 * Schedule Command の重複起動・再実行に対する二重配信防止に使う。
 */
class MeetingReminder extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'meeting_id',
        'window',
        'sent_at',
    ];

    protected $casts = [
        'window' => MeetingReminderWindow::class,
        'sent_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
