<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Services\PaymentGatewayRegistry;
use App\Services\StoryPurchaseService;
use Illuminate\Http\Request;

class StoryPurchaseController extends Controller
{
    public function __construct(
        private StoryPurchaseService $purchases,
        private PaymentGatewayRegistry $gateways,
    ) {}

    public function checkout(Request $request, string $slug)
    {
        $story = Story::where('slug', $slug)->firstOrFail();
        $user = $this->requireUser($request);

        if (! $story->isPurchasable()) {
            return $this->error('not_purchasable', 'This story is not available for direct purchase.', 422);
        }

        if ($this->purchases->hasPurchased($user, $story)) {
            return $this->error('already_purchased', 'You already own this story.', 422);
        }

        $data = $request->validate([
            'gateway' => ['required', 'in:'.implode(',', $this->gateways->names())],
        ]);

        try {
            $result = $this->purchases->initiateCheckout($data['gateway'], $user, $story);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            report($e);

            return $this->error('gateway_error', 'Could not start checkout with the selected payment provider. Please try again.', 502);
        } catch (\App\Exceptions\UnknownPaymentGatewayException $e) {
            return $this->error('unknown_gateway', $e->getMessage(), 422);
        }

        return $this->ok($result, 201);
    }

    /** Whether the current user already owns this story outright - used by the frontend to hide/show the buy button. */
    public function status(Request $request, string $slug)
    {
        $story = Story::where('slug', $slug)->firstOrFail();
        $user = $this->requireUser($request);

        // Resolved for this reader's own currency (see Story::priceFor) -
        // not a single fixed price for everyone, since an author can price a
        // story differently per currency (see StoryManagementController).
        $price = $story->isPurchasable() ? $story->priceFor($user->currency) : null;

        return $this->ok([
            'purchasable' => $story->isPurchasable(),
            'price' => $price?->amount,
            'currency' => $price?->currency,
            'purchased' => $this->purchases->hasPurchased($user, $story),
        ]);
    }
}
