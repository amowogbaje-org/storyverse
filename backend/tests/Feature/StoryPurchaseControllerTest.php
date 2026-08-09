<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Models\PenName;
use App\Models\Story;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PaymentGatewayRegistry;
use App\Support\CheckoutSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\CreatesStoryFixtures;
use Tests\TestCase;

class StoryPurchaseControllerTest extends TestCase
{
    use CreatesStoryFixtures;

    /** Swaps the real gateway registry for one containing only a fake, in-memory gateway - see PaymentGatewayServiceTest for why this is the pattern rather than mocking HTTP for the happy path. $failCheckout, when true, makes the fake actually call out over HTTP to a Http::fake()'d failing endpoint - so the resulting Illuminate\Http\Client\RequestException is the real one Laravel's client throws, not a hand-built stand-in for it. */
    private function bindFakeGateway(bool $failCheckout = false): void
    {
        if ($failCheckout) {
            Http::fake(['fake-gateway.test/*' => Http::response(['message' => 'bad request'], 400)]);
        }

        $fake = new class($failCheckout) implements PaymentGateway
        {
            public function __construct(private bool $failCheckout) {}

            public function name(): string
            {
                return 'fake';
            }

            public function createCheckoutSession(User $user, SubscriptionPlan $plan): CheckoutSession
            {
                throw new \RuntimeException('not used in these tests');
            }

            public function createTipCheckoutSession(User $tipper, PenName $penName, float $amount, string $currency): CheckoutSession
            {
                throw new \RuntimeException('not used in these tests');
            }

            public function createStoryPurchaseCheckoutSession(User $user, Story $story, float $amount, string $currency): CheckoutSession
            {
                if ($this->failCheckout) {
                    Http::post('https://fake-gateway.test/checkout')->throw();
                }

                return new CheckoutSession(
                    "https://fake.test/checkout/{$story->slug}?amount={$amount}&currency={$currency}",
                    "fake-ref-{$story->id}-{$currency}"
                );
            }

            public function verifySignature(Request $request): bool
            {
                return true;
            }

            public function extractSuccessfulReference(Request $request): ?string
            {
                return null;
            }
        };

        $this->app->instance(PaymentGatewayRegistry::class, new PaymentGatewayRegistry([$fake]));
    }

    public function test_checkout_resolves_the_price_in_the_readers_own_currency(): void
    {
        $this->bindFakeGateway();
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'premium']);
        $story->prices()->create(['currency' => 'USD', 'amount' => 9.99]);
        $story->prices()->create(['currency' => 'NGN', 'amount' => 4500]);
        $reader = $this->createReader(['currency' => 'NGN']);

        $response = $this->postJson("/api/v1/stories/{$story->slug}/purchase", ['gateway' => 'fake'], $this->bearerFor($reader));

        $response->assertStatus(201)
            ->assertJsonPath('data.checkout_url', "https://fake.test/checkout/{$story->slug}?amount=4500&currency=NGN");

        $this->assertDatabaseHas('story_purchases', [
            'user_id' => $reader->id, 'story_id' => $story->id,
            'amount' => 4500, 'currency' => 'NGN', 'status' => 'pending',
        ]);
    }

    public function test_checkout_falls_back_to_usd_when_the_readers_currency_was_not_priced(): void
    {
        $this->bindFakeGateway();
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'premium']);
        $story->prices()->create(['currency' => 'USD', 'amount' => 9.99]);
        $reader = $this->createReader(['currency' => 'PHP']);

        $response = $this->postJson("/api/v1/stories/{$story->slug}/purchase", ['gateway' => 'fake'], $this->bearerFor($reader));

        $response->assertStatus(201);
        $this->assertDatabaseHas('story_purchases', ['user_id' => $reader->id, 'amount' => 9.99, 'currency' => 'USD']);
    }

    public function test_checkout_rejects_a_story_with_no_price_set(): void
    {
        $this->bindFakeGateway();
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'premium']);
        $reader = $this->createReader();

        $this->postJson("/api/v1/stories/{$story->slug}/purchase", ['gateway' => 'fake'], $this->bearerFor($reader))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'not_purchasable');
    }

    public function test_checkout_rejects_a_story_the_reader_already_owns(): void
    {
        $this->bindFakeGateway();
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'premium']);
        $story->prices()->create(['currency' => 'USD', 'amount' => 9.99]);
        $reader = $this->createReader();
        $story->purchases()->create([
            'user_id' => $reader->id, 'gateway' => 'fake', 'gateway_reference' => 'already-owned',
            'amount' => 9.99, 'currency' => 'USD', 'status' => 'success',
        ]);

        $this->postJson("/api/v1/stories/{$story->slug}/purchase", ['gateway' => 'fake'], $this->bearerFor($reader))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'already_purchased');
    }

    public function test_checkout_returns_502_when_the_gateway_call_fails(): void
    {
        $this->bindFakeGateway(failCheckout: true);

        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'premium']);
        $story->prices()->create(['currency' => 'USD', 'amount' => 9.99]);
        $reader = $this->createReader();

        $this->postJson("/api/v1/stories/{$story->slug}/purchase", ['gateway' => 'fake'], $this->bearerFor($reader))
            ->assertStatus(502)
            ->assertJsonPath('error.code', 'gateway_error');
    }

    public function test_status_reports_the_price_resolved_for_the_readers_currency_and_whether_they_already_own_it(): void
    {
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'premium']);
        $story->prices()->create(['currency' => 'USD', 'amount' => 9.99]);
        $story->prices()->create(['currency' => 'GBP', 'amount' => 7.99]);
        $reader = $this->createReader(['currency' => 'GBP']);

        $this->getJson("/api/v1/stories/{$story->slug}/purchase-status", $this->bearerFor($reader))
            ->assertStatus(200)
            ->assertJsonPath('data.purchasable', true)
            ->assertJsonPath('data.currency', 'GBP')
            ->assertJsonPath('data.price', '7.99')
            ->assertJsonPath('data.purchased', false);
    }

    public function test_status_reports_not_purchasable_for_a_free_story(): void
    {
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'free']);
        $reader = $this->createReader();

        $this->getJson("/api/v1/stories/{$story->slug}/purchase-status", $this->bearerFor($reader))
            ->assertStatus(200)
            ->assertJsonPath('data.purchasable', false)
            ->assertJsonPath('data.price', null);
    }
}
