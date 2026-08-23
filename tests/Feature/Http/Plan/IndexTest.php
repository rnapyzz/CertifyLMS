<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_index(): void
    {
        $admin = User::factory()->admin()->create();
        Plan::factory()->published()->create(['name' => '3 ヶ月プラン']);

        $response = $this->actingAs($admin)->get(route('admin.plans.index'));

        $response->assertOk();
        $response->assertViewIs('plan.management.index');
        $response->assertSee('3 ヶ月プラン');
    }

    public function test_student_and_coach_are_forbidden(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();

        $this->actingAs($student)->get(route('admin.plans.index'))->assertForbidden();
        $this->actingAs($coach)->get(route('admin.plans.index'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.plans.index'))->assertRedirect(route('login'));
    }

    public function test_keyword_filters_by_name(): void
    {
        $admin = User::factory()->admin()->create();
        Plan::factory()->published()->create(['name' => '1 ヶ月プラン']);
        Plan::factory()->published()->create(['name' => '12 ヶ月プラン']);

        $response = $this->actingAs($admin)->get(route('admin.plans.index', ['keyword' => '12 ヶ月']));

        $response->assertOk();
        $response->assertSee('12 ヶ月プラン');
        $response->assertDontSee('1 ヶ月プラン');
    }

    public function test_status_filter_returns_only_matching_status(): void
    {
        $admin = User::factory()->admin()->create();
        Plan::factory()->draft()->create(['name' => '下書き中プラン']);
        Plan::factory()->published()->create(['name' => '公開中プラン']);

        $response = $this->actingAs($admin)->get(route('admin.plans.index', ['status' => 'draft']));

        $response->assertOk();
        $response->assertSee('下書き中プラン');
        $response->assertDontSee('公開中プラン');
    }

    public function test_shows_contracted_user_count_per_row(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();
        User::factory()->student()->count(2)->create(['plan_id' => $plan->id]);

        $response = $this->actingAs($admin)->get(route('admin.plans.index'));

        $response->assertOk();
        $response->assertViewHas('plans', function ($plans) use ($plan) {
            return $plans->firstWhere('id', $plan->id)->users_count === 2;
        });
    }
}
