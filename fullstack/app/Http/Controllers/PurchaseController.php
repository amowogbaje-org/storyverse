<?php

namespace App\Http\Controllers;

use App\Models\Story;
use App\Services\PaymentGatewayRegistry;
use App\Services\StoryPurchaseService;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(
        private StoryPurchaseService $purchases,
        private PaymentGatewayRegistry $gateways,
    ) {}

    public function checkout(Request $request, string $slug)
    {
        $story = Story::where('slug', $slug)->firstOrFail();
        $user = $request->user();

        abort_if(! $story->isPurchasable(), 422, 'This story is not available for direct purchase.');

        if ($this->purchases->hasPurchased($user, $story)) {
            return redirect()->route('stories.show', $story->slug);
        }

        $data = $request->validate([
            'gateway' => ['required', 'in:'.implode(',', $this->gateways->names())],
        ]);

        try {
            $result = $this->purchases->initiateCheckout($data['gateway'], $user, $story);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            report($e);

            return back()->withErrors(['gateway' => 'Could not start checkout with that payment provider. Please try again.']);
        }

        // Hosted checkout - straight redirect to the gateway's own payment
        // page, no client-side SDK/JS needed on our end.
        return redirect()->away($result['checkout_url']);
    }
}
