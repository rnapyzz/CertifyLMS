<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Http\Requests\QaThread\StoreRequest;
use App\Http\Requests\QaThread\UpdateRequest;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use App\UseCases\QaThread\DestroyAction;
use App\UseCases\QaThread\IndexAction;
use App\UseCases\QaThread\ResolveAction;
use App\UseCases\QaThread\StoreAction;
use App\UseCases\QaThread\UnresolveAction;
use App\UseCases\QaThread\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * 質問掲示板 Controller。受講生 / コーチ / admin 共通で利用される。
 *
 * - index / show: 3 ロール共通導線。admin は `/admin/qa-board` 配下から同じ action を呼ぶ(閲覧範囲は
 *   `QaThread::scopeVisibleTo()` / `QaThreadPolicy::view` でロールごとに絞る)
 * - create / store / edit / update / resolve / unresolve: 投稿者(受講生)本人専用
 * - destroy: 投稿者本人(未回答の場合のみ)と admin(モデレーション、件数制限なし)の両方から呼ばれる。
 *   分岐は `QaThreadPolicy::delete` 側に閉じ込め、Controller はリダイレクト先だけを route context で出し分ける
 */
class QaBoardController extends Controller
{
    public function index(Request $request, IndexAction $action): View
    {
        /** @var User $viewer */
        $viewer = auth()->user();
        $isAdminContext = $request->routeIs('admin.*');

        $filters = $request->only(['status', 'certification_id', 'keyword']);
        $threads = $action($viewer, $filters);
        $certifications = $this->certificationOptions($viewer, $isAdminContext);

        return view('qa-thread.index', [
            'filters' => $filters,
            'certifications' => $certifications,
            'publishedStatus' => CertificationStatus::Published,
            'threads' => $threads,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', QaThread::class);

        $certifications = Certification::query()->published()->orderBy('name')->get();

        return view('qa-thread.create', compact('certifications'));
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $thread = $action($user, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を投稿しました。');
    }

    public function show(QaThread $thread): View
    {
        $this->authorize('view', $thread);

        $thread->load(['user', 'certification', 'replies.user']);

        return view('qa-thread.show', compact('thread'));
    }

    public function edit(QaThread $thread): View
    {
        $this->authorize('update', $thread);

        return view('qa-thread.edit', compact('thread'));
    }

    public function update(QaThread $thread, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($thread, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を更新しました。');
    }

    public function destroy(Request $request, QaThread $thread, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $thread);

        $isAdminContext = $request->routeIs('admin.*');

        $action($thread);

        return redirect()
            ->route($isAdminContext ? 'admin.qa-board.index' : 'qa-board.index')
            ->with('success', '質問を削除しました。');
    }

    public function resolve(QaThread $thread, ResolveAction $action): RedirectResponse
    {
        $this->authorize('resolve', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を解決済にしました。');
    }

    public function unresolve(QaThread $thread, UnresolveAction $action): RedirectResponse
    {
        $this->authorize('unresolve', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を未解決に戻しました。');
    }

    /**
     * 一覧の資格フィルタ選択肢。admin = 全資格(公開停止中含む) / coach = 担当かつ公開済資格のみ / student = 公開済資格のみ。
     */
    private function certificationOptions(User $viewer, bool $isAdminContext): Collection
    {
        if ($isAdminContext) {
            return Certification::query()->orderBy('name')->get();
        }

        if ($viewer->role === UserRole::Coach) {
            return $viewer->assignedCertifications()->published()->orderBy('name')->get();
        }

        return Certification::query()->published()->orderBy('name')->get();
    }
}
