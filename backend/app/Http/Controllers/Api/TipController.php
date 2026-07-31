<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PenName;
use App\Services\PaymentGatewayRegistry;
use App\Services\TipService;
use Illuminate\Http\Request;

class TipController extends Controller
{
    public function __construct(
        private TipService $tips,
        private PaymentGatewayRegistry $gateways,
    ) {}

    public function checkout(Request $request, string $slug)
    {
        $penName = PenName::where('slug', $slug)->firstOrFail();
        $user = $this->requireUser($request);

        if ($penName->user_id === $user->id) {
            return $this->error('cannot_tip_self', "You can't tip your own pen name.", 422);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.5', 'max:10000'],
            'currency' => ['required', 'in:USD,GBP,NGN'],
            'gateway' => ['required', 'in:'.implode(',', $this->gateways->names())],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->tips->initiateCheckout(
            $data['gateway'],
            $user,
            $penName,
            (float) $data['amount'],
            $data['currency'],
            $data['message'] ?? null
        );

        return $this->ok($result, 201);
    }
}
