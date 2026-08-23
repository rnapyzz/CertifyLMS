<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_update_name_and_bio(): void
    {
        $student = User::factory()->student()->inProgress()->create(['name' => '旧名前', 'bio' => null]);

        $response = $this->actingAs($student)->patch(route('settings.profile.update'), [
            'name' => '新しい名前',
            'bio' => '自己紹介文です。',
        ]);

        $response->assertRedirect(route('settings.profile.edit'));
        $student->refresh();
        $this->assertSame('新しい名前', $student->name);
        $this->assertSame('自己紹介文です。', $student->bio);
    }

    public function test_email_cannot_be_changed_from_this_screen(): void
    {
        $student = User::factory()->student()->inProgress()->create(['email' => 'original@certify-lms.test']);

        $this->actingAs($student)->patch(route('settings.profile.update'), [
            'name' => $student->name,
            'email' => 'changed@certify-lms.test',
        ]);

        $this->assertSame('original@certify-lms.test', $student->fresh()->email);
    }

    public function test_name_is_required(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)->patch(route('settings.profile.update'), [
            'name' => '',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_coach_can_update_meeting_url(): void
    {
        $coach = User::factory()->coach()->create(['meeting_url' => null]);

        $response = $this->actingAs($coach)->patch(route('settings.profile.update'), [
            'name' => $coach->name,
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        ]);

        $response->assertRedirect(route('settings.profile.edit'));
        $this->assertSame('https://meet.google.com/abc-defg-hij', $coach->fresh()->meeting_url);
    }

    public function test_student_cannot_set_meeting_url_even_if_submitted_directly(): void
    {
        $student = User::factory()->student()->inProgress()->create(['meeting_url' => null]);

        $this->actingAs($student)->patch(route('settings.profile.update'), [
            'name' => $student->name,
            'meeting_url' => 'https://meet.google.com/should-be-ignored',
        ]);

        $this->assertNull($student->fresh()->meeting_url);
    }

    public function test_admin_cannot_set_meeting_url_even_if_submitted_directly(): void
    {
        $admin = User::factory()->admin()->create(['meeting_url' => null]);

        $this->actingAs($admin)->patch(route('settings.profile.update'), [
            'name' => $admin->name,
            'meeting_url' => 'https://meet.google.com/should-be-ignored',
        ]);

        $this->assertNull($admin->fresh()->meeting_url);
    }

    public function test_graduated_student_can_still_update_profile(): void
    {
        $graduated = User::factory()->student()->create(['status' => UserStatus::Graduated->value]);

        $response = $this->actingAs($graduated)->patch(route('settings.profile.update'), [
            'name' => '卒業後の氏名変更',
        ]);

        $response->assertRedirect(route('settings.profile.edit'));
        $this->assertSame('卒業後の氏名変更', $graduated->fresh()->name);
    }
}
