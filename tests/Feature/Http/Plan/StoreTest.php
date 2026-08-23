<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_plan_as_draft(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => '3 ヶ月プラン 12 回',
            'description' => '説明文',
            'duration_days' => 90,
            'default_meeting_quota' => 12,
            'sort_order' => 20,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('plans', [
            'name' => '3 ヶ月プラン 12 回',
            'duration_days' => 90,
            'default_meeting_quota' => 12,
            'status' => 'draft',
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_name_is_required(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), [
            'duration_days' => 30,
            'default_meeting_quota' => 4,
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('plans', 0);
    }

    public function test_duration_days_must_be_within_range(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'テストプラン',
            'duration_days' => 0,
            'default_meeting_quota' => 4,
        ]);

        $response->assertSessionHasErrors('duration_days');
    }

    public function test_default_meeting_quota_must_be_within_range(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'テストプラン',
            'duration_days' => 30,
            'default_meeting_quota' => -1,
        ]);

        $response->assertSessionHasErrors('default_meeting_quota');
    }

    public function test_coach_and_student_are_forbidden(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $payload = ['name' => 'テストプラン', 'duration_days' => 30, 'default_meeting_quota' => 4];

        $this->actingAs($coach)->post(route('admin.plans.store'), $payload)->assertForbidden();
        $this->actingAs($student)->post(route('admin.plans.store'), $payload)->assertForbidden();
        $this->assertDatabaseCount('plans', 0);
    }
}
