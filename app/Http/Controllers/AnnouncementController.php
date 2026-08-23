<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\Announcement\StoreRequest;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use App\UseCases\Announcement\StoreAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * admin 用のお知らせ配信管理 Controller。一覧 / 新規配信フォーム / 配信実行 / 詳細のみを提供する
 * (配信は不可逆な仕様のため edit / update / destroy は持たない)。
 */
class AnnouncementController extends Controller
{
    public function index(): View
    {
        $announcements = Announcement::query()
            ->with(['targetCertification', 'targetUser', 'createdBy'])
            ->latest('dispatched_at')
            ->paginate(20);

        return view('announcement.management.index', [
            'announcements' => $announcements,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Announcement::class);

        return view('announcement.management.create', [
            'certifications' => Certification::query()->published()->orderBy('name')->get(),
            'students' => User::query()
                ->where('role', UserRole::Student)
                ->where('status', UserStatus::InProgress)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $announcement = $action($request->user(), $request->validated());

        return redirect()
            ->route('admin.announcements.show', $announcement)
            ->with('success', "お知らせを配信しました({$announcement->dispatched_count} 件)。");
    }

    public function show(Announcement $announcement): View
    {
        $this->authorize('view', $announcement);

        return view('announcement.management.show', [
            'announcement' => $announcement->load(['targetCertification', 'targetUser', 'createdBy']),
        ]);
    }
}
