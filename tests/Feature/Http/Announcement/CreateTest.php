<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_create_form(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.announcements.create'));

        $response->assertOk();
    }

    public function test_coach_and_student_are_forbidden(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $this->actingAs($coach)->get(route('admin.announcements.create'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.announcements.create'))->assertForbidden();
    }
}
