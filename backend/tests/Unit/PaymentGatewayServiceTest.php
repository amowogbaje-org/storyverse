<?php

namespace Tests\Unit;

use App\Contracts\PaymentGateway;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PaymentGatewayRegistry;
use App\Services\PaymentGatewayService;
use App\Support\CheckoutSession;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Demonstrates the point of depending on the PaymentGateway interface rather
 * than a concrete Stripe/Paystack/Flutterwave class: PaymentGatewayService
 * can be fully tested with an in-memory fake gateway and zero HTTP mocking,
 * because it never talks to the network itself - only the gateway
 * implementations do.
 */
class PaymentGatewayServiceTest extends TestCase
{
    public function test_initiate_checkout_records_a_pending_payment_using_whatever_gateway_the_registry_resolves(): void
    {
        $fakeGateway = new class implements PaymentGateway
        {
            public function name(): string
            {
                return 'fake';
            }

            public function createCheckoutSession(User $user, SubscriptionPlan $plan): CheckoutSession
            {
                return new CheckoutSession('https://fake.test/checkout/xyz', 'fake-ref-xyz');
            }

            public function verifySignature(Request $request): bool
            {
                return true;
            }

            public function extractSuccessfulReference(Request $request): ?string
            {
                return 'fake-ref-xyz';
            }
        };

        $service = new PaymentGatewayService(new PaymentGatewayRegistry([$fakeGateway]));

        $user = User::create(['name' => 'Fake User', 'email' => 'fake@example.com', 'password' => bcrypt('x'), 'role' => 'reader']);
        $plan = SubscriptionPlan::create([
            'name' => 'Fake Plan', 'country_code' => 'US', 'currency' => 'USD',
            'price' => 5, 'billing_interval' => 'month', 'is_active' => true,
        ]);

        $result = $service->initiateCheckout('fake', $user, $plan);

        $this->assertSame('https://fake.test/checkout/xyz', $result['checkout_url']);
        $this->assertSame('fake-ref-xyz', $result['reference']);
        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id, 'gateway' => 'fake', 'gateway_reference' => 'fake-ref-xyz', 'status' => 'pending',
        ]);
    }
}
