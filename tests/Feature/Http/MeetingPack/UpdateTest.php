<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_basic_info(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->draft()->create(['name' => '旧名称']);

        $response = $this->actingAs($admin)->patch(route('admin.meeting-packs.update', $plan), [
            'name' => '新名称',
            'description' => '更新後の説明',
            'meeting_count' => 3,
            'price' => 9000,
            'sort_order' => 5,
        ]);

        $response->assertRedirect(route('admin.meeting-packs.show', $plan));
        $plan->refresh();
        $this->assertSame('新名称', $plan->name);
        $this->assertSame(3, $plan->meeting_count);
        $this->assertSame(9000, $plan->price);
        $this->assertSame($admin->id, $plan->updated_by_user_id);
    }

    public function test_update_does_not_change_status(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->published()->create();

        $this->actingAs($admin)->patch(route('admin.meeting-packs.update', $plan), [
            'name' => $plan->name,
            'meeting_count' => $plan->meeting_count,
            'price' => $plan->price,
            'status' => 'archived',
        ]);

        $this->assertSame('published', $plan->fresh()->status->value);
    }

    public function test_coach_and_student_are_forbidden(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $plan = MeetingPack::factory()->draft()->create();
        $payload = ['name' => 'X', 'meeting_count' => 1, 'price' => 1000];

        $this->actingAs($coach)->patch(route('admin.meeting-packs.update', $plan), $payload)->assertForbidden();
        $this->actingAs($student)->patch(route('admin.meeting-packs.update', $plan), $payload)->assertForbidden();
    }
}
