<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_pack_as_draft(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.store'), [
            'name' => '5 回パック',
            'description' => '説明文',
            'meeting_count' => 5,
            'price' => 12000,
            'stripe_price_id' => 'price_123',
            'sort_order' => 20,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('meeting_packs', [
            'name' => '5 回パック',
            'meeting_count' => 5,
            'price' => 12000,
            'status' => 'draft',
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_name_is_required(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.store'), [
            'meeting_count' => 5,
            'price' => 12000,
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('meeting_packs', 0);
    }

    public function test_meeting_count_must_be_within_range(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.store'), [
            'name' => 'テストパック',
            'meeting_count' => 0,
            'price' => 1000,
        ]);

        $response->assertSessionHasErrors('meeting_count');
    }

    public function test_price_must_be_within_range(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.store'), [
            'name' => 'テストパック',
            'meeting_count' => 1,
            'price' => -1,
        ]);

        $response->assertSessionHasErrors('price');
    }

    public function test_coach_and_student_are_forbidden(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $payload = ['name' => 'テストパック', 'meeting_count' => 1, 'price' => 1000];

        $this->actingAs($coach)->post(route('admin.meeting-packs.store'), $payload)->assertForbidden();
        $this->actingAs($student)->post(route('admin.meeting-packs.store'), $payload)->assertForbidden();
        $this->assertDatabaseCount('meeting_packs', 0);
    }
}
