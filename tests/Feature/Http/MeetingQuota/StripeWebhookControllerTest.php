<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\User;
use App\Services\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Event as StripeEvent;
use Stripe\Exception\SignatureVerificationException;
use Tests\TestCase;

/**
 * Stripe Webhook 受信を検証する。署名検証(`StripeCheckoutService::constructWebhookEvent`)は
 * Mockery で差し替え、実際の署名計算は行わない(Controller は検証済の Event を受け取る前提のテスト)。
 */
class StripeWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private function fakeEvent(string $type, array $object): StripeEvent
    {
        return StripeEvent::constructFrom([
            'id' => 'evt_test_'.uniqid(),
            'type' => $type,
            'data' => ['object' => $object],
        ]);
    }

    public function test_completed_event_marks_payment_completed_and_grants_quota(): void
    {
        $student = User::factory()->student()->create();
        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'stripe_checkout_session_id' => 'cs_test_webhook_1',
            'quantity' => 5,
            'status' => PaymentStatus::Pending->value,
        ]);

        $event = $this->fakeEvent('checkout.session.completed', [
            'id' => 'cs_test_webhook_1',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_test_webhook_1',
        ]);

        $mock = Mockery::mock(StripeCheckoutService::class);
        $mock->shouldReceive('constructWebhookEvent')->once()->andReturn($event);
        $this->app->instance(StripeCheckoutService::class, $mock);

        $response = $this->postJson(route('webhooks.stripe'), [], ['Stripe-Signature' => 'sig']);

        $response->assertOk();
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertSame('pi_test_webhook_1', $payment->fresh()->stripe_payment_intent_id);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'type' => MeetingQuotaTransactionType::Purchased->value,
            'amount' => 5,
            'related_payment_id' => $payment->id,
        ]);
    }

    public function test_duplicate_completed_events_do_not_double_grant_quota(): void
    {
        $student = User::factory()->student()->create();
        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'stripe_checkout_session_id' => 'cs_test_webhook_2',
            'quantity' => 5,
            'status' => PaymentStatus::Pending->value,
        ]);

        $event = $this->fakeEvent('checkout.session.completed', [
            'id' => 'cs_test_webhook_2',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_test_webhook_2',
        ]);

        $mock = Mockery::mock(StripeCheckoutService::class);
        $mock->shouldReceive('constructWebhookEvent')->twice()->andReturn($event);
        $this->app->instance(StripeCheckoutService::class, $mock);

        $this->postJson(route('webhooks.stripe'), [], ['Stripe-Signature' => 'sig'])->assertOk();
        $this->postJson(route('webhooks.stripe'), [], ['Stripe-Signature' => 'sig'])->assertOk();

        $this->assertSame(1, MeetingQuotaTransaction::where('related_payment_id', $payment->id)->count());
    }

    public function test_expired_event_marks_pending_payment_as_failed_without_granting_quota(): void
    {
        $student = User::factory()->student()->create();
        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'stripe_checkout_session_id' => 'cs_test_webhook_3',
            'status' => PaymentStatus::Pending->value,
        ]);

        $event = $this->fakeEvent('checkout.session.expired', [
            'id' => 'cs_test_webhook_3',
        ]);

        $mock = Mockery::mock(StripeCheckoutService::class);
        $mock->shouldReceive('constructWebhookEvent')->once()->andReturn($event);
        $this->app->instance(StripeCheckoutService::class, $mock);

        $this->postJson(route('webhooks.stripe'), [], ['Stripe-Signature' => 'sig'])->assertOk();

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_unknown_event_type_is_ignored_without_error(): void
    {
        $event = $this->fakeEvent('customer.created', ['id' => 'cus_test_1']);

        $mock = Mockery::mock(StripeCheckoutService::class);
        $mock->shouldReceive('constructWebhookEvent')->once()->andReturn($event);
        $this->app->instance(StripeCheckoutService::class, $mock);

        $this->postJson(route('webhooks.stripe'), [], ['Stripe-Signature' => 'sig'])->assertOk();
    }

    public function test_completed_event_for_unknown_session_does_not_error(): void
    {
        $event = $this->fakeEvent('checkout.session.completed', [
            'id' => 'cs_test_does_not_exist',
            'payment_status' => 'paid',
        ]);

        $mock = Mockery::mock(StripeCheckoutService::class);
        $mock->shouldReceive('constructWebhookEvent')->once()->andReturn($event);
        $this->app->instance(StripeCheckoutService::class, $mock);

        $this->postJson(route('webhooks.stripe'), [], ['Stripe-Signature' => 'sig'])->assertOk();
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_refunded_event_reverts_quota_and_marks_payment_refunded(): void
    {
        $student = User::factory()->student()->create();
        $payment = Payment::factory()->completed()->create([
            'user_id' => $student->id,
            'stripe_checkout_session_id' => 'cs_test_webhook_4',
            'stripe_payment_intent_id' => 'pi_test_webhook_4',
            'quantity' => 5,
        ]);
        MeetingQuotaTransaction::factory()->purchased(amount: 5, paymentId: $payment->id)->create([
            'user_id' => $student->id,
        ]);

        $event = $this->fakeEvent('charge.refunded', [
            'id' => 'ch_test_webhook_4',
            'payment_intent' => 'pi_test_webhook_4',
        ]);

        $mock = Mockery::mock(StripeCheckoutService::class);
        $mock->shouldReceive('constructWebhookEvent')->once()->andReturn($event);
        $this->app->instance(StripeCheckoutService::class, $mock);

        $this->postJson(route('webhooks.stripe'), [], ['Stripe-Signature' => 'sig'])->assertOk();

        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'related_payment_id' => $payment->id,
            'type' => MeetingQuotaTransactionType::Refunded->value,
            'amount' => -5,
        ]);
    }

    public function test_duplicate_refunded_events_do_not_double_revert_quota(): void
    {
        $student = User::factory()->student()->create();
        $payment = Payment::factory()->completed()->create([
            'user_id' => $student->id,
            'stripe_checkout_session_id' => 'cs_test_webhook_5',
            'stripe_payment_intent_id' => 'pi_test_webhook_5',
            'quantity' => 5,
        ]);

        $event = $this->fakeEvent('charge.refunded', [
            'id' => 'ch_test_webhook_5',
            'payment_intent' => 'pi_test_webhook_5',
        ]);

        $mock = Mockery::mock(StripeCheckoutService::class);
        $mock->shouldReceive('constructWebhookEvent')->twice()->andReturn($event);
        $this->app->instance(StripeCheckoutService::class, $mock);

        $this->postJson(route('webhooks.stripe'), [], ['Stripe-Signature' => 'sig'])->assertOk();
        $this->postJson(route('webhooks.stripe'), [], ['Stripe-Signature' => 'sig'])->assertOk();

        $this->assertSame(
            1,
            MeetingQuotaTransaction::where('related_payment_id', $payment->id)
                ->where('type', MeetingQuotaTransactionType::Refunded->value)
                ->count(),
        );
    }

    public function test_invalid_signature_returns_400_and_does_not_require_authentication(): void
    {
        $mock = Mockery::mock(StripeCheckoutService::class);
        $mock->shouldReceive('constructWebhookEvent')
            ->once()
            ->andThrow(SignatureVerificationException::factory('invalid signature'));
        $this->app->instance(StripeCheckoutService::class, $mock);

        $this->postJson(route('webhooks.stripe'), [], ['Stripe-Signature' => 'bad-sig'])
            ->assertStatus(400);
    }
}
