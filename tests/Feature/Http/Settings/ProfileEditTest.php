<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_profile_settings(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)->get(route('settings.profile.edit'));

        $response->assertOk();
        $response->assertViewIs('settings.profile');
    }

    public function test_coach_can_view_profile_settings(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)->get(route('settings.profile.edit'))->assertOk();
    }

    public function test_admin_can_view_profile_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('settings.profile.edit'))->assertOk();
    }

    public function test_graduated_student_can_still_view_profile_settings(): void
    {
        $graduated = User::factory()->student()->create(['status' => UserStatus::Graduated->value]);

        $this->actingAs($graduated)->get(route('settings.profile.edit'))->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('settings.profile.edit'))->assertRedirect(route('login'));
    }
}
