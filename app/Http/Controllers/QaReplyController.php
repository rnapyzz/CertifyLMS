<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\QaReply\StoreRequest;
use App\Http\Requests\QaReply\UpdateRequest;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\UseCases\QaReply\DestroyAction;
use App\UseCases\QaReply\StoreAction;
use App\UseCases\QaReply\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 質問掲示板の回答 Controller。QaThread の子リソースとして操作する。
 *
 * - store: 受講生 / コーチ(担当かつ公開済資格のみ)。管理者は回答できない
 * - edit / update: 投稿者本人のみ
 * - destroy: 投稿者本人、または admin(モデレーション)。分岐は `QaReplyPolicy::delete` に閉じ込める
 */
class QaReplyController extends Controller
{
    public function store(QaThread $thread, StoreRequest $request, StoreAction $action): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $action($user, $thread, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '回答を投稿しました。');
    }

    public function edit(QaThread $thread, QaReply $reply): View
    {
        $this->assertBelongsToThread($thread, $reply);
        $this->authorize('update', $reply);

        return view('qa-thread.reply-edit', compact('thread', 'reply'));
    }

    public function update(QaThread $thread, QaReply $reply, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $this->assertBelongsToThread($thread, $reply);

        $action($reply, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '回答を更新しました。');
    }

    public function destroy(Request $request, QaThread $thread, QaReply $reply, DestroyAction $action): RedirectResponse
    {
        $this->assertBelongsToThread($thread, $reply);
        $this->authorize('delete', $reply);

        $isAdminContext = $request->routeIs('admin.*');

        $action($reply);

        return redirect()
            ->route($isAdminContext ? 'admin.qa-board.show' : 'qa-board.show', $thread)
            ->with('success', '回答を削除しました。');
    }

    private function assertBelongsToThread(QaThread $thread, QaReply $reply): void
    {
        abort_unless($reply->qa_thread_id === $thread->id, 404);
    }
}
