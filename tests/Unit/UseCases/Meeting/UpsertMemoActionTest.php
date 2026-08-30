<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Meeting;

use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\MeetingMemo;
use App\UseCases\Meeting\UpsertMemoAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpsertMemoActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_memo_for_reserved_meeting(): void
    {
        $meeting = Meeting::factory()->reserved()->create();

        $memo = (new UpsertMemoAction)($meeting, 'よく理解できていました。');

        $this->assertSame('よく理解できていました。', $memo->body);
        $this->assertDatabaseHas('meeting_memos', [
            'meeting_id' => $meeting->id,
            'body' => 'よく理解できていました。',
        ]);
    }

    public function test_updates_existing_memo_instead_of_duplicating(): void
    {
        $meeting = Meeting::factory()->reserved()->create();
        MeetingMemo::factory()->forMeeting($meeting)->create(['body' => '旧メモ']);

        (new UpsertMemoAction)($meeting, '新しいメモ');

        $this->assertSame(1, MeetingMemo::query()->where('meeting_id', $meeting->id)->count());
        $this->assertDatabaseHas('meeting_memos', ['meeting_id' => $meeting->id, 'body' => '新しいメモ']);
    }

    public function test_allows_memo_on_completed_meeting(): void
    {
        $meeting = Meeting::factory()->completed()->create();

        $memo = (new UpsertMemoAction)($meeting, '完了後の振り返り');

        $this->assertSame('完了後の振り返り', $memo->body);
    }

    public function test_rejects_memo_on_canceled_meeting(): void
    {
        $meeting = Meeting::factory()->canceled()->create();

        $this->expectException(MeetingStatusTransitionException::class);

        (new UpsertMemoAction)($meeting, 'メモ');
    }
}
