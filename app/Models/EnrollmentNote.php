<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EnrollmentNoteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 受講登録配下のコーチメモ。担当コーチ / 管理者が受講生の日常観察を時系列で記録する業務記録。
 * 受講生本人には一切見せない(コーチ → 管理者の内部記録)。フラットな本文のみで、状態 / 添付 / 履歴を持たない。
 *
 * 関連: Enrollment(親) / User(author、作成者。コーチ離任後も author_id は書き換えない)
 */
class EnrollmentNote extends Model
{
    /** @use HasFactory<EnrollmentNoteFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'enrollment_id',
        'author_id',
        'body',
    ];

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
