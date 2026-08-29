<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Exceptions\Mentoring\GoogleCalendarSyncException;
use App\Models\GoogleCredential;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * コーチの Google カレンダー連携(OAuth 認可フロー)を検証する。
 * 実際の Google API へは通信せず、`GoogleCalendarService` を Mockery で差し替えて振る舞いを固定する。
 */
class GoogleCalendarControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_requires_coach_role(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('settings.google-calendar.redirect'))
            ->assertForbidden();
    }

    public function test_redirect_stores_state_in_session_and_redirects_to_google(): void
    {
        $coach = User::factory()->coach()->create();

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldReceive('buildAuthorizationUrl')
            ->once()
            ->withArgs(fn ($state, $redirectUri) => is_string($state) && $state !== '' && is_string($redirectUri))
            ->andReturn('https://accounts.google.com/o/oauth2/auth?mock=1');
        $this->app->instance(GoogleCalendarService::class, $mock);

        $response = $this->actingAs($coach)->get(route('settings.google-calendar.redirect'));

        $response->assertRedirect('https://accounts.google.com/o/oauth2/auth?mock=1');
        $this->assertNotNull(session('google_calendar_oauth_state'));
    }

    public function test_redirect_ignores_unsafe_redirect_path(): void
    {
        $coach = User::factory()->coach()->create();

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldReceive('buildAuthorizationUrl')->andReturn('https://accounts.google.com/mock');
        $this->app->instance(GoogleCalendarService::class, $mock);

        $this->actingAs($coach)
            ->get(route('settings.google-calendar.redirect', ['redirect_path' => '//evil.example.com']));

        $this->assertNull(session('google_calendar_oauth_redirect_path'));
    }

    public function test_callback_rejects_mismatched_state(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)
            ->withSession(['google_calendar_oauth_state' => 'expected-state'])
            ->get(route('settings.google-calendar.callback', ['state' => 'wrong-state', 'code' => 'abc']))
            ->assertForbidden();

        $this->assertDatabaseCount('google_credentials', 0);
    }

    public function test_callback_shows_error_flash_when_google_denies_consent(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)
            ->withSession(['google_calendar_oauth_state' => 'state-1'])
            ->get(route('settings.google-calendar.callback', ['state' => 'state-1', 'error' => 'access_denied']));

        $response->assertRedirect(route('settings.availability.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('google_credentials', 0);
    }

    public function test_callback_creates_credential_on_success(): void
    {
        $coach = User::factory()->coach()->create();

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldReceive('exchangeCode')
            ->once()
            ->andReturn([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3600,
            ]);
        $this->app->instance(GoogleCalendarService::class, $mock);

        $response = $this->actingAs($coach)
            ->withSession(['google_calendar_oauth_state' => 'state-1'])
            ->get(route('settings.google-calendar.callback', ['state' => 'state-1', 'code' => 'auth-code']));

        $response->assertRedirect(route('settings.availability.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('google_credentials', [
            'user_id' => $coach->id,
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'calendar_id' => 'primary',
        ]);
    }

    public function test_callback_redirects_to_custom_redirect_path(): void
    {
        $coach = User::factory()->coach()->create();

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldReceive('exchangeCode')->once()->andReturn([
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'expires_in' => 3600,
        ]);
        $this->app->instance(GoogleCalendarService::class, $mock);

        $response = $this->actingAs($coach)
            ->withSession([
                'google_calendar_oauth_state' => 'state-1',
                'google_calendar_oauth_redirect_path' => '/settings/availability',
            ])
            ->get(route('settings.google-calendar.callback', ['state' => 'state-1', 'code' => 'auth-code']));

        $response->assertRedirect('/settings/availability');
    }

    public function test_callback_shows_error_when_token_exchange_fails(): void
    {
        $coach = User::factory()->coach()->create();

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldReceive('exchangeCode')->once()->andThrow(new GoogleCalendarSyncException('failed'));
        $this->app->instance(GoogleCalendarService::class, $mock);

        $response = $this->actingAs($coach)
            ->withSession(['google_calendar_oauth_state' => 'state-1'])
            ->get(route('settings.google-calendar.callback', ['state' => 'state-1', 'code' => 'auth-code']));

        $response->assertRedirect(route('settings.availability.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('google_credentials', 0);
    }

    public function test_destroy_deletes_credential_and_calls_revoke(): void
    {
        $coach = User::factory()->coach()->create();
        $credential = GoogleCredential::factory()->for($coach)->create();

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldReceive('revoke')->once()->with(Mockery::on(fn ($c) => $c->id === $credential->id));
        $this->app->instance(GoogleCalendarService::class, $mock);

        $response = $this->actingAs($coach)->delete(route('settings.google-calendar.destroy'));

        $response->assertRedirect(route('settings.availability.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('google_credentials', ['id' => $credential->id]);
    }

    public function test_destroy_without_existing_credential_is_a_noop(): void
    {
        $coach = User::factory()->coach()->create();

        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldNotReceive('revoke');
        $this->app->instance(GoogleCalendarService::class, $mock);

        $this->actingAs($coach)
            ->delete(route('settings.google-calendar.destroy'))
            ->assertRedirect(route('settings.availability.index'));
    }

    public function test_destroy_requires_coach_role(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->delete(route('settings.google-calendar.destroy'))
            ->assertForbidden();
    }
}
