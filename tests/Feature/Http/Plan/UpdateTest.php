<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_basic_info(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create(['name' => '旧名称']);

        $response = $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'name' => '新名称',
            'description' => '更新後の説明',
            'duration_days' => 60,
            'default_meeting_quota' => 8,
            'sort_order' => 5,
        ]);

        $response->assertRedirect(route('admin.plans.show', $plan));
        $plan->refresh();
        $this->assertSame('新名称', $plan->name);
        $this->assertSame(60, $plan->duration_days);
        $this->assertSame(8, $plan->default_meeting_quota);
        $this->assertSame($admin->id, $plan->updated_by_user_id);
    }

    public function test_update_does_not_change_status(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'name' => $plan->name,
            'duration_days' => $plan->duration_days,
            'default_meeting_quota' => $plan->default_meeting_quota,
            'status' => 'archived',
        ]);

        $this->assertSame('published', $plan->fresh()->status->value);
    }

    public function test_coach_and_student_are_forbidden(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $plan = Plan::factory()->draft()->create();
        $payload = ['name' => 'X', 'duration_days' => 30, 'default_meeting_quota' => 4];

        $this->actingAs($coach)->put(route('admin.plans.update', $plan), $payload)->assertForbidden();
        $this->actingAs($student)->put(route('admin.plans.update', $plan), $payload)->assertForbidden();
    }
}
