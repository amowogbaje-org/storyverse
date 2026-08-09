<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GatewayAvailabilityService;
use Illuminate\Http\Request;

/**
 * Despite the name, this no longer handles actual subscriptions (browsing
 * plans, checkout, cancel) - that whole product was removed in favor of
 * buying stories individually (see story_prices/StoryPurchaseController).
 * What's left, gateways(), is generic "which payment providers work in this
 * country" info that both story purchases and tips also depend on, so it
 * stayed here rather than being deleted along with the rest. Not renamed to
 * avoid a broader churn of route/import changes for a name-only cleanup.
 */
class SubscriptionController extends Controller
{
    public function __construct(private GatewayAvailabilityService $gateways) {}

    public function gateways(Request $request)
    {
        $user = $this->currentUser($request);
        $countryCode = $user?->country_code ?? $request->query('country_code', 'US');

        return $this->ok($this->gateways->availableGateways($countryCode));
    }
}
