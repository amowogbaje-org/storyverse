<?php

namespace App\Http\Controllers\Api;

use App\Contracts\SupportsPayouts;
use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Services\PaymentGatewayRegistry;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    /** Author's own payout history. */
    public function mine(Request $request)
    {
        $user = $this->requireUser($request);

        return $this->ok($user->payouts()->orderByDesc('period_start')->get());
    }

    /**
     * Author updates their own payout account. bank_code is optional - only
     * needed if automated transfers ever get switched on
     * (config('payouts.auto_send_enabled')); without it, payouts still get
     * generated and can be paid manually using the bank name/account number.
     */
    public function updateAccount(Request $request)
    {
        $user = $this->requireUser($request);

        $data = $request->validate([
            'payout_account_name' => ['required', 'string', 'max:255'],
            'payout_account_number' => ['required', 'string', 'max:34'],
            'payout_bank_name' => ['required', 'string', 'max:255'],
            'payout_bank_code' => ['nullable', 'string', 'max:20'],
        ]);

        $user->update($data);

        return $this->ok([
            'payout_account_name' => $user->payout_account_name,
            'payout_account_number' => $user->payout_account_number,
            'payout_bank_name' => $user->payout_bank_name,
            'payout_bank_code' => $user->payout_bank_code,
        ]);
    }

    /** Admin: every payout, most recent period first. */
    public function index(Request $request)
    {
        $query = Payout::with('user')->orderByDesc('period_start');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $this->paginated($query->cursorPaginate(30), fn (Payout $p) => [
            'id' => $p->id,
            'author' => $p->user->display_name,
            'period_start' => $p->period_start,
            'period_end' => $p->period_end,
            'subscription_share_amount' => $p->subscription_share_amount,
            'tips_amount' => $p->tips_amount,
            'total_amount' => $p->total_amount,
            'currency' => $p->currency,
            'status' => $p->status,
            'payout_account_name' => $p->payout_account_name,
            'payout_account_number' => $p->payout_account_number,
            'payout_bank_name' => $p->payout_bank_name,
            'failure_reason' => $p->failure_reason,
            'paid_at' => $p->paid_at,
        ]);
    }

    /** Admin: mark a payout as paid by hand (e.g. after doing the bank transfer manually). */
    public function markPaid(int $id)
    {
        $payout = Payout::findOrFail($id);
        $payout->update(['status' => 'paid', 'paid_at' => now()]);

        return $this->ok($payout);
    }

    /** Admin: manually (re)trigger an automated transfer for one payout - e.g. retrying a failed one. */
    public function send(int $id, PaymentGatewayRegistry $gateways)
    {
        $payout = Payout::findOrFail($id);

        if (! $payout->payout_account_number || ! $payout->payout_bank_code) {
            return $this->error('no_payout_account', 'This author has no payout account (or no bank code) on file.', 422);
        }

        $gateway = collect($gateways->names())
            ->map(fn ($name) => $gateways->get($name))
            ->first(fn ($g) => $g instanceof SupportsPayouts);

        if (! $gateway) {
            return $this->error('no_payout_gateway', 'No registered payment gateway supports sending payouts.', 422);
        }

        try {
            $transferId = $gateway->sendPayout($payout);
            $payout->update(['status' => 'processing', 'gateway_transfer_id' => $transferId, 'failure_reason' => null]);
        } catch (\Throwable $e) {
            $payout->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);

            return $this->error('transfer_failed', $e->getMessage(), 422);
        }

        return $this->ok($payout);
    }
}
