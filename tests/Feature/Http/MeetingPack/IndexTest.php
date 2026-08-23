<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_index(): void
    {
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->published()->create(['name' => '5 回パック']);

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index'));

        $response->assertOk();
        $response->assertViewIs('meeting-pack.management.index');
        $response->assertSee('5 回パック');
    }

    public function test_student_and_coach_are_forbidden(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();

        $this->actingAs($student)->get(route('admin.meeting-packs.index'))->assertForbidden();
        $this->actingAs($coach)->get(route('admin.meeting-packs.index'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.meeting-packs.index'))->assertRedirect(route('login'));
    }

    public function test_keyword_filters_by_name(): void
    {
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->published()->create(['name' => '3 回パック']);
        MeetingPack::factory()->published()->create(['name' => '10 回パック']);

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index', ['keyword' => '10 回']));

        $response->assertOk();
        $response->assertSee('10 回パック');
        $response->assertDontSee('3 回パック');
    }

    public function test_status_filter_returns_only_matching_status(): void
    {
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->draft()->create(['name' => '下書き中パック']);
        MeetingPack::factory()->published()->create(['name' => '公開中パック']);

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index', ['status' => 'draft']));

        $response->assertOk();
        $response->assertSee('下書き中パック');
        $response->assertDontSee('公開中パック');
    }
}
