<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_announcement_history(): void
    {
        $admin = User::factory()->admin()->create();
        Announcement::factory()->create(['created_by_user_id' => $admin->id, 'title' => '配信済みお知らせ']);

        $response = $this->actingAs($admin)->get(route('admin.announcements.index'));

        $response->assertOk();
        $response->assertSee('配信済みお知らせ');
    }

    public function test_coach_and_student_are_forbidden(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $this->actingAs($coach)->get(route('admin.announcements.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.announcements.index'))->assertForbidden();
    }
}
