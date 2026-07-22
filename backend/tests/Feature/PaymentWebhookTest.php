<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\CreatesStoryFixtures;
use Tests\TestCase;

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

    public function test_checkout_creates_a_pending_payment_and_returns_a_checkout_url(): void
    {
        Http::fake([
            'api.paystack.co/*' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://paystack.test/pay/abc123'],
            ], 200),
        ]);

        $reader = $this->createReader();
        $plan = $this->makePlan();

        $response = $this->postJson('/api/v1/subscriptions/checkout', [
            'plan_id' => $plan->id,
            'gateway' => 'paystack',
        ], $this->bearerFor($reader));

        $response->assertStatus(200)->assertJsonPath('data.checkout_url', 'https://paystack.test/pay/abc123');

        $this->assertDatabaseHas('payments', [
            'user_id' => $reader->id,
            'plan_id' => $plan->id,
            'gateway' => 'paystack',
            'status' => 'pending',
        ]);
    }

    public function test_checkout_returns_502_when_the_gateway_call_fails(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['message' => 'bad request'], 400)]);

        $reader = $this->createReader();
        $plan = $this->makePlan();

        $this->postJson('/api/v1/subscriptions/checkout', [
            'plan_id' => $plan->id,
            'gateway' => 'paystack',
        ], $this->bearerFor($reader))->assertStatus(502);
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

    public function test_checkout_rejects_a_gateway_name_the_registry_does_not_know(): void
    {
        $reader = $this->createReader();
        $plan = $this->makePlan();

        $this->postJson('/api/v1/subscriptions/checkout', [
            'plan_id' => $plan->id,
            'gateway' => 'dogecoin',
        ], $this->bearerFor($reader))->assertStatus(422);
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
