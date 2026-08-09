<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\SubscriptionPlan;
use Tests\Feature\Concerns\CreatesStoryFixtures;
use Tests\TestCase;

/**
 * The subscribe-to-a-plan checkout flow itself (/subscriptions/checkout) was
 * removed along with the rest of the subscription product - stories are
 * bought individually now (see StoryPurchaseController). What's left here
 * tests webhook processing (signature verification, activating a
 * Subscription from a successful charge) - that machinery was deliberately
 * kept intact for historical/in-flight data even though nothing creates a
 * new pending Payment via HTTP anymore, so these tests create one directly
 * instead of going through the removed route.
 */
class PaymentWebhookTest extends TestCase
{
    use CreatesStoryFixtures;

    private function makePlan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Premium', 'country_code' => 'NG', 'currency' => 'NGN',
            'price' => 5000, 'billing_interval' => 'month', 'is_active' => true,
        ]);
    }

    public function test_paystack_webhook_rejects_an_invalid_signature(): void
    {
        config(['services.paystack.secret_key' => 'test-secret']);

        $this->postJson('/api/v1/webhooks/paystack', ['event' => 'charge.success'], [
            'x-paystack-signature' => 'not-the-right-signature',
        ])->assertStatus(400);
    }

    public function test_an_unknown_gateway_name_returns_404_not_a_500(): void
    {
        $this->postJson('/api/v1/webhooks/some-gateway-that-does-not-exist', [])
            ->assertStatus(404);
    }

    public function test_the_registry_knows_all_three_built_in_gateways(): void
    {
        $registry = app(\App\Services\PaymentGatewayRegistry::class);

        $this->assertEqualsCanonicalizing(['stripe', 'paystack', 'flutterwave'], $registry->names());
        $this->assertTrue($registry->has('stripe'));
        $this->assertFalse($registry->has('dogecoin'));
    }

    public function test_paystack_webhook_activates_a_subscription_on_a_valid_signature(): void
    {
        config(['services.paystack.secret_key' => 'test-secret']);

        $reader = $this->createReader();
        $plan = $this->makePlan();

        $payment = Payment::create([
            'user_id' => $reader->id,
            'plan_id' => $plan->id,
            'gateway' => 'paystack',
            'gateway_reference' => 'ref_abc123',
            'amount' => $plan->price,
            'currency' => $plan->currency,
            'status' => 'pending',
        ]);

        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'ref_abc123']]);
        $signature = hash_hmac('sha512', $body, 'test-secret');

        $this->call('POST', '/api/v1/webhooks/paystack', [], [], [], [
            'HTTP_x-paystack-signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertStatus(200);

        $payment->refresh();
        $this->assertSame('success', $payment->status);
        $this->assertNotNull($payment->subscription_id);
        $this->assertDatabaseHas('subscriptions', ['user_id' => $reader->id, 'status' => 'active']);
    }

    public function test_replaying_the_same_webhook_does_not_create_a_second_subscription(): void
    {
        config(['services.paystack.secret_key' => 'test-secret']);

        $reader = $this->createReader();
        $plan = $this->makePlan();

        $payment = Payment::create([
            'user_id' => $reader->id, 'plan_id' => $plan->id, 'gateway' => 'paystack',
            'gateway_reference' => 'ref_replay', 'amount' => $plan->price, 'currency' => $plan->currency,
            'status' => 'pending',
        ]);

        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'ref_replay']]);
        $signature = hash_hmac('sha512', $body, 'test-secret');

        $headers = ['HTTP_x-paystack-signature' => $signature, 'CONTENT_TYPE' => 'application/json'];

        $this->call('POST', '/api/v1/webhooks/paystack', [], [], [], $headers, $body)->assertStatus(200);
        $this->call('POST', '/api/v1/webhooks/paystack', [], [], [], $headers, $body)->assertStatus(200);

        $this->assertSame(1, \App\Models\Subscription::where('user_id', $reader->id)->count());
    }
}
