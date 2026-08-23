<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_announcement_detail(): void
    {
        $admin = User::factory()->admin()->create();
        $announcement = Announcement::factory()->create([
            'created_by_user_id' => $admin->id,
            'title' => '詳細確認用お知らせ',
            'body' => '本文の全文',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.announcements.show', $announcement));

        $response->assertOk();
        $response->assertSee('詳細確認用お知らせ');
        $response->assertSee('本文の全文');
    }

    public function test_coach_and_student_are_forbidden(): void
    {
        $admin = User::factory()->admin()->create();
        $announcement = Announcement::factory()->create(['created_by_user_id' => $admin->id]);
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $this->actingAs($coach)->get(route('admin.announcements.show', $announcement))->assertForbidden();
        $this->actingAs($student)->get(route('admin.announcements.show', $announcement))->assertForbidden();
    }
}
