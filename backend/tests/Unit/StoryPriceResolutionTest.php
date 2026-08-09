<?php

namespace Tests\Unit;

use Tests\Feature\Concerns\CreatesStoryFixtures;
use Tests\TestCase;

class StoryPriceResolutionTest extends TestCase
{
    use CreatesStoryFixtures;

    public function test_not_purchasable_with_no_prices_set(): void
    {
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'premium']);

        $this->assertFalse($story->isPurchasable());
        $this->assertNull($story->priceFor('USD'));
    }

    public function test_resolves_the_readers_own_currency_when_the_author_set_one(): void
    {
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'premium']);
        $story->prices()->create(['currency' => 'USD', 'amount' => 9.99]);
        $story->prices()->create(['currency' => 'NGN', 'amount' => 4500]);

        $this->assertSame('4500.00', $story->priceFor('NGN')->amount);
    }

    public function test_falls_back_to_usd_when_the_readers_currency_was_not_priced(): void
    {
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'premium']);
        $story->prices()->create(['currency' => 'USD', 'amount' => 9.99]);

        $price = $story->priceFor('NGN');

        $this->assertSame('USD', $price->currency);
        $this->assertSame('9.99', $price->amount);
    }

    public function test_falls_back_to_whatever_price_exists_when_neither_the_readers_currency_nor_usd_is_set(): void
    {
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'premium']);
        $story->prices()->create(['currency' => 'GBP', 'amount' => 7.5]);

        $price = $story->priceFor('NGN');

        $this->assertSame('GBP', $price->currency);
        $this->assertTrue($story->isPurchasable());
    }

    public function test_resolves_sensibly_for_a_reader_with_no_currency_set(): void
    {
        ['story' => $story] = $this->createStoryWithEpisodes(['access_type' => 'premium']);
        $story->prices()->create(['currency' => 'USD', 'amount' => 9.99]);

        $this->assertSame('USD', $story->priceFor(null)->currency);
    }
}
