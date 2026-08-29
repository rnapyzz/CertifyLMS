<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingQuota;

use App\Enums\UserStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use App\Services\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Exception\ApiConnectionException;
use Tests\TestCase;

/**
 * 追加面談パックの購入(Checkout Session 作成への委譲)を検証する。
 * 実際の Stripe API へは通信せず `StripeCheckoutService` を Mockery で差し替える
 * (`GoogleCalendarService` のテストと同じ方針)。
 */
class MeetingQuotaCheckoutControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_select_shows_only_published_packs(): void
    {
        $student = User::factory()->student()->create();
        $published = MeetingPack::factory()->published()->create(['name' => '公開中パック']);
        MeetingPack::factory()->draft()->create(['name' => '下書きパック']);
        MeetingPack::factory()->archived()->create(['name' => 'アーカイブ済パック']);

        $response = $this->actingAs($student)->get(route('meeting-quota.checkout.select'));

        $response->assertOk();
        $response->assertViewHas('plans', function ($plans) use ($published) {
            return $plans->count() === 1 && $plans->first()->id === $published->id;
        });
    }

    public function test_select_is_blocked_for_non_student(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)
            ->get(route('meeting-quota.checkout.select'))
            ->assertForbidden();
    }

    public function test_create_redirects_to_stripe_and_creates_pending_payment(): void
    {
        $student = User::factory()->student()->create();
        $pack = MeetingPack::factory()->published()->create(['meeting_count' => 5, 'price' => 12000]);

        $session = StripeCheckoutSession::constructFrom([
            'id' => 'cs_test_123',
            'url' => 'https://checkout.stripe.com/pay/cs_test_123',
        ]);

        $mock = Mockery::mock(StripeCheckoutService::class);
        $mock->shouldReceive('createCheckoutSession')
            ->once()
            ->withArgs(fn ($user, $p) => $user->id === $student->id && $p->id === $pack->id)
            ->andReturn($session);
        $this->app->instance(StripeCheckoutService::class, $mock);

        $response = $this->actingAs($student)->post(route('meeting-quota.checkout.create'), [
            'meeting_pack_id' => $pack->id,
        ]);

        $response->assertRedirect('https://checkout.stripe.com/pay/cs_test_123');
        $this->assertDatabaseHas('payments', [
            'user_id' => $student->id,
            'meeting_pack_id' => $pack->id,
            'stripe_checkout_session_id' => 'cs_test_123',
            'quantity' => 5,
            'amount' => 12000,
            'status' => 'pending',
        ]);
    }

    public function test_create_rejects_draft_pack_even_with_direct_url(): void
    {
        $student = User::factory()->student()->create();
        $pack = MeetingPack::factory()->draft()->create();

        $mock = Mockery::mock(StripeCheckoutService::class);
        $mock->shouldNotReceive('createCheckoutSession');
        $this->app->instance(StripeCheckoutService::class, $mock);

        $response = $this->actingAs($student)->post(route('meeting-quota.checkout.create'), [
            'meeting_pack_id' => $pack->id,
        ]);

        $response->assertSessionHasErrors('meeting_pack_id');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_create_rejects_archived_pack(): void
    {
        $student = User::factory()->student()->create();
        $pack = MeetingPack::factory()->archived()->create();

        $mock = Mockery::mock(StripeCheckoutService::class);
        $mock->shouldNotReceive('createCheckoutSession');
        $this->app->instance(StripeCheckoutService::class, $mock);

        $this->actingAs($student)
            ->post(route('meeting-quota.checkout.create'), ['meeting_pack_id' => $pack->id])
            ->assertSessionHasErrors('meeting_pack_id');
    }

    public function test_create_rejects_nonexistent_pack(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->post(route('meeting-quota.checkout.create'), ['meeting_pack_id' => 'does-not-exist'])
            ->assertSessionHasErrors('meeting_pack_id');
    }

    public function test_create_is_blocked_for_non_active_learning_student(): void
    {
        $graduated = User::factory()->student()->create(['status' => UserStatus::Graduated->value]);
        $pack = MeetingPack::factory()->published()->create();

        $this->actingAs($graduated)
            ->post(route('meeting-quota.checkout.create'), ['meeting_pack_id' => $pack->id])
            ->assertForbidden();
    }

    public function test_create_shows_flash_error_and_creates_no_payment_when_stripe_api_fails(): void
    {
        $student = User::factory()->student()->create();
        $pack = MeetingPack::factory()->published()->create();

        $mock = Mockery::mock(StripeCheckoutService::class);
        $mock->shouldReceive('createCheckoutSession')
            ->once()
            ->andThrow(new ApiConnectionException('network error'));
        $this->app->instance(StripeCheckoutService::class, $mock);

        $response = $this->actingAs($student)->post(route('meeting-quota.checkout.create'), [
            'meeting_pack_id' => $pack->id,
        ]);

        $response->assertRedirect(route('meeting-quota.checkout.select'));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_create_shows_flash_error_when_stripe_secret_key_is_not_configured(): void
    {
        // StripeCheckoutService を Mockery で差し替えず、実際の Stripe SDK を通す回帰テスト。
        // 空文字の api_key は Stripe\Exception\ApiErrorException の subclass ではない
        // Stripe\Exception\InvalidArgumentException を投げるため、Controller 側の catch 節が
        // ApiErrorException だけを見ていると素の 500 になってしまう(実際に一度この不具合が発生した)。
        config(['services.stripe.secret' => '']);

        $student = User::factory()->student()->create();
        $pack = MeetingPack::factory()->published()->create();

        $response = $this->actingAs($student)->post(route('meeting-quota.checkout.create'), [
            'meeting_pack_id' => $pack->id,
        ]);

        $response->assertRedirect(route('meeting-quota.checkout.select'));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_success_shows_payment_summary_for_owner(): void
    {
        $student = User::factory()->student()->create();
        $payment = Payment::factory()->completed()->create([
            'user_id' => $student->id,
            'stripe_checkout_session_id' => 'cs_test_success_1',
        ]);

        $response = $this->actingAs($student)->get(route('meeting-quota.checkout.success', ['session_id' => 'cs_test_success_1']));

        $response->assertOk();
        $response->assertViewHas('payment', fn ($p) => $p !== null && $p->id === $payment->id);
    }

    public function test_success_hides_payment_belonging_to_another_user(): void
    {
        $owner = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        Payment::factory()->completed()->create([
            'user_id' => $owner->id,
            'stripe_checkout_session_id' => 'cs_test_success_2',
        ]);

        $response = $this->actingAs($other)->get(route('meeting-quota.checkout.success', ['session_id' => 'cs_test_success_2']));

        $response->assertOk();
        $response->assertViewHas('payment', null);
    }

    public function test_success_without_session_id_still_renders(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('meeting-quota.checkout.success'));

        $response->assertOk();
        $response->assertViewHas('payment', null);
    }
}
