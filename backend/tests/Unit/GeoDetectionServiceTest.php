<?php

namespace Tests\Unit;

use App\Services\GeoDetectionService;
use Illuminate\Http\Request;
use Tests\TestCase;

class GeoDetectionServiceTest extends TestCase
{
    private function requestWithCfCountry(string $country): Request
    {
        return Request::create('/', 'GET', server: ['HTTP_CF_IPCOUNTRY' => $country]);
    }

    public function test_resolves_currency_from_the_cf_ipcountry_header(): void
    {
        $result = app(GeoDetectionService::class)->detect($this->requestWithCfCountry('NG'));

        $this->assertSame('NG', $result['country_code']);
        $this->assertSame('NGN', $result['currency']);
    }

    /** @dataProvider countryCurrencyProvider */
    public function test_resolves_currency_for_every_supported_country(string $country, string $expectedCurrency): void
    {
        $result = app(GeoDetectionService::class)->detect($this->requestWithCfCountry($country));

        $this->assertSame($expectedCurrency, $result['currency']);
    }

    public static function countryCurrencyProvider(): array
    {
        return [
            'United States' => ['US', 'USD'],
            'United Kingdom' => ['GB', 'GBP'],
            'Nigeria' => ['NG', 'NGN'],
            'Philippines' => ['PH', 'PHP'],
            'India' => ['IN', 'INR'],
            'Indonesia' => ['ID', 'IDR'],
            'Canada' => ['CA', 'CAD'],
        ];
    }

    public function test_falls_back_to_usd_for_an_unrecognized_country(): void
    {
        $result = app(GeoDetectionService::class)->detect($this->requestWithCfCountry('ZZ'));

        $this->assertSame('USD', $result['currency']);
    }

    public function test_falls_back_to_us_when_nothing_in_the_request_identifies_a_country(): void
    {
        $result = app(GeoDetectionService::class)->detect(Request::create('/', 'GET'));

        $this->assertSame('US', $result['country_code']);
        $this->assertSame('USD', $result['currency']);
    }

    public function test_resolves_country_from_browser_locale_when_no_ip_signal_is_present(): void
    {
        $result = app(GeoDetectionService::class)->detect(
            Request::create('/', 'GET', ['browser_locale' => 'en-CA'])
        );

        $this->assertSame('CA', $result['country_code']);
        $this->assertSame('CAD', $result['currency']);
    }

    /** The CF-IPCountry header, when present, always wins over browser_locale. */
    public function test_cf_ipcountry_header_takes_priority_over_browser_locale(): void
    {
        $request = Request::create('/', 'GET', ['browser_locale' => 'en-CA'], server: ['HTTP_CF_IPCOUNTRY' => 'NG']);

        $result = app(GeoDetectionService::class)->detect($request);

        $this->assertSame('NG', $result['country_code']);
        $this->assertSame('NGN', $result['currency']);
    }

    /**
     * Every currency this resolves to must actually be one config/currencies.php
     * (and therefore StoryManagementController/StoryPurchaseController) knows
     * about - the whole point of the fallback-to-USD safety net in resolve().
     */
    public function test_never_resolves_to_a_currency_outside_the_supported_list(): void
    {
        $supported = array_keys(config('currencies'));

        foreach (array_column(self::countryCurrencyProvider(), 1) as $currency) {
            $this->assertContains($currency, $supported);
        }
    }
}
