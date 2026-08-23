<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_change_password_with_correct_current_password(): void
    {
        $user = User::factory()->student()->inProgress()->create([
            'password' => Hash::make('old-password-123'),
        ]);

        $response = $this->actingAs($user)->put(route('settings.password.update'), [
            'current_password' => 'old-password-123',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ]);

        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'password']));
        $this->assertTrue(Hash::check('new-password-456', $user->fresh()->password));
    }

    public function test_rejects_incorrect_current_password(): void
    {
        $user = User::factory()->student()->inProgress()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->actingAs($user)->put(route('settings.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ]);

        $response->assertSessionHasErrorsIn('updatePassword', 'current_password');
        $this->assertTrue(Hash::check('correct-password', $user->fresh()->password));
    }

    public function test_rejects_mismatched_confirmation(): void
    {
        $user = User::factory()->student()->inProgress()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->actingAs($user)->put(route('settings.password.update'), [
            'current_password' => 'correct-password',
            'password' => 'new-password-456',
            'password_confirmation' => 'does-not-match',
        ]);

        $response->assertSessionHasErrorsIn('updatePassword', 'password');
        $this->assertTrue(Hash::check('correct-password', $user->fresh()->password));
    }

    public function test_coach_can_change_own_password(): void
    {
        $coach = User::factory()->coach()->create(['password' => Hash::make('coach-old-pass')]);

        $response = $this->actingAs($coach)->put(route('settings.password.update'), [
            'current_password' => 'coach-old-pass',
            'password' => 'coach-new-pass-1',
            'password_confirmation' => 'coach-new-pass-1',
        ]);

        $response->assertRedirect();
        $this->assertTrue(Hash::check('coach-new-pass-1', $coach->fresh()->password));
    }
}
