<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\EnrollmentNote\StoreRequest;
use App\Http\Requests\EnrollmentNote\UpdateRequest;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\UseCases\EnrollmentNote\DestroyAction;
use App\UseCases\EnrollmentNote\StoreAction;
use App\UseCases\EnrollmentNote\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * コーチメモ(EnrollmentNote)の CRUD Controller。担当コーチ / 管理者専用、受講生は閲覧含め一切アクセス不可。
 * 単独の一覧 / 詳細画面は持たず、受講登録詳細画面(`enrollments.show`)内に一覧・追加フォームを表示する。
 */
class EnrollmentNoteController extends Controller
{
    public function store(Enrollment $enrollment, StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $action($enrollment, $request->user(), $request->validated());

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', 'メモを追加しました。');
    }

    public function edit(EnrollmentNote $note): View
    {
        $this->authorize('update', $note);

        return view('enrollment-note.edit', compact('note'));
    }

    public function update(EnrollmentNote $note, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($note, $request->validated());

        return redirect()
            ->route('enrollments.show', $note->enrollment_id)
            ->with('success', 'メモを更新しました。');
    }

    public function destroy(EnrollmentNote $note, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $note);

        $enrollmentId = $note->enrollment_id;
        $action($note);

        return redirect()
            ->route('enrollments.show', $enrollmentId)
            ->with('success', 'メモを削除しました。');
    }
}
