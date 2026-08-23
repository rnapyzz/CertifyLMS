<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\EnrollmentNote;
use Illuminate\Support\Facades\DB;

/**
 * コーチメモの本文を更新するユースケース。作成者(author_id)は書き換えない。
 */
final class UpdateAction
{
    /**
     * @param array{body: string} $validated
     */
    public function __invoke(EnrollmentNote $note, array $validated): EnrollmentNote
    {
        return DB::transaction(function () use ($note, $validated) {
            $note->update(['body' => $validated['body']]);

            return $note->fresh();
        });
    }
}
