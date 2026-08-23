<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_show(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->published()->create();

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.show', $plan));

        $response->assertOk();
        $response->assertViewIs('meeting-pack.management.show');
        $response->assertSee($plan->name);
    }

    public function test_student_and_coach_are_forbidden(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $plan = MeetingPack::factory()->published()->create();

        $this->actingAs($student)->get(route('admin.meeting-packs.show', $plan))->assertForbidden();
        $this->actingAs($coach)->get(route('admin.meeting-packs.show', $plan))->assertForbidden();
    }
}
