<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_show_with_contracted_users(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();
        $student = User::factory()->student()->create(['plan_id' => $plan->id]);

        $response = $this->actingAs($admin)->get(route('admin.plans.show', $plan));

        $response->assertOk();
        $response->assertViewIs('plan.management.show');
        $response->assertSee($plan->name);
        $response->assertSee($student->email);
    }

    public function test_student_and_coach_are_forbidden(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $plan = Plan::factory()->published()->create();

        $this->actingAs($student)->get(route('admin.plans.show', $plan))->assertForbidden();
        $this->actingAs($coach)->get(route('admin.plans.show', $plan))->assertForbidden();
    }
}
