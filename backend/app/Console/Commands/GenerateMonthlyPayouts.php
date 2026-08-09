<?php

namespace App\Console\Commands;

use App\Contracts\SupportsPayouts;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\ReadingProgress;
use App\Models\Story;
use App\Models\StoryPurchase;
use App\Models\Tip;
use App\Models\User;
use App\Notifications\PayoutIssued;
use App\Services\PaymentGatewayRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Two revenue sources, combined per author per currency: direct story sales
 * (StoryPurchase - attributed exactly, since a purchase is for one specific
 * story) and tips. A third, subscription_share_amount, is computed the same
 * way it always was (a completed-reads proportional share of Payment
 * revenue) and always comes out to 0 for any period after subscriptions
 * were removed as a product - kept rather than deleted so past payout
 * records still mean what they said at the time, not silently reinterpreted.
 *
 * Scheduled for the 2nd of each month (routes/console.php) - a day after
 * month-end rather than exactly on the 1st, so the previous month's data has
 * fully settled (a purchase or a completed read logged in the last minutes
 * of the month should still count).
 */
class GenerateMonthlyPayouts extends Command
{
    protected $signature = 'app:generate-monthly-payouts {--month= : YYYY-MM to generate for, defaults to last calendar month}';

    protected $description = "Generate each author's payout for last month's story sales share and tips";

    public function handle(PaymentGatewayRegistry $gateways): int
    {
        $month = $this->option('month')
            ? \Carbon\Carbon::createFromFormat('Y-m', $this->option('month'))->startOfMonth()
            : now()->subMonthNoOverflow()->startOfMonth();

        $periodStart = $month->copy()->startOfMonth();
        $periodEnd = $month->copy()->endOfMonth();
        $authorSharePercent = (float) config('payouts.author_share_percentage');

        $platformCompletedReads = ReadingProgress::whereBetween('completed_at', [$periodStart, $periodEnd])->count();

        // Always 0 for any period after subscriptions were removed - see the
        // class docblock. Left in place rather than deleted so this doesn't
        // need to change again if subscription_share_amount's historical
        // meaning is ever needed.
        $platformRevenue = Payment::where('status', 'success')
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->select('currency', DB::raw('sum(amount) as total'))
            ->groupBy('currency')
            ->pluck('total', 'currency');

        $authors = User::whereIn('role', ['author', 'admin'])->get()
            ->filter(fn ($u) => $u->penNames()->exists());

        $created = 0;

        foreach ($authors as $author) {
            $storyIds = Story::whereIn('pen_name_id', $author->penNames()->pluck('id'))->pluck('id');

            $authorCompletedReads = ReadingProgress::whereIn('story_id', $storyIds)
                ->whereBetween('completed_at', [$periodStart, $periodEnd])
                ->count();

            $share = $platformCompletedReads > 0 ? $authorCompletedReads / $platformCompletedReads : 0.0;

            $salesByCurrency = StoryPurchase::whereIn('story_id', $storyIds)
                ->where('status', 'success')
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->select('currency', DB::raw('sum(amount) as total'))
                ->groupBy('currency')
                ->pluck('total', 'currency');

            $tipsByCurrency = Tip::whereIn('pen_name_id', $author->penNames()->pluck('id'))
                ->where('status', 'success')
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->select('currency', DB::raw('sum(amount) as total'))
                ->groupBy('currency')
                ->pluck('total', 'currency');

            $currencies = $platformRevenue->keys()
                ->merge($salesByCurrency->keys())
                ->merge($tipsByCurrency->keys())
                ->unique();

            foreach ($currencies as $currency) {
                $subscriptionShare = round(($platformRevenue[$currency] ?? 0) * $share, 2);
                $storySales = round(($salesByCurrency[$currency] ?? 0) * $authorSharePercent, 2);
                $tips = round((float) ($tipsByCurrency[$currency] ?? 0), 2);
                $total = $subscriptionShare + $storySales + $tips;

                if ($total < (config("payouts.minimum_payout_amount.{$currency}") ?? 0)) {
                    continue;
                }

                $payout = Payout::firstOrCreate(
                    ['user_id' => $author->id, 'period_start' => $periodStart->toDateString(), 'period_end' => $periodEnd->toDateString(), 'currency' => $currency],
                    [
                        'subscription_share_amount' => $subscriptionShare,
                        'story_sales_amount' => $storySales,
                        'tips_amount' => $tips,
                        'total_amount' => $total,
                        'status' => 'pending',
                        'payout_account_name' => $author->payout_account_name,
                        'payout_account_number' => $author->payout_account_number,
                        'payout_bank_name' => $author->payout_bank_name,
                        'payout_bank_code' => $author->payout_bank_code,
                    ]
                );

                if (! $payout->wasRecentlyCreated) {
                    continue;
                }

                $created++;
                $author->notify(new PayoutIssued($payout));

                $this->maybeAutoSend($payout, $author, $gateways);
            }
        }

        $this->info("Generated {$created} payout(s) for {$periodStart->format('F Y')}.");

        return self::SUCCESS;
    }

    private function maybeAutoSend(Payout $payout, User $author, PaymentGatewayRegistry $gateways): void
    {
        if (! config('payouts.auto_send_enabled')) {
            return; // stays 'pending' for an admin to action manually - see config/payouts.php
        }

        if (! $payout->payout_account_number || ! $payout->payout_bank_code) {
            $payout->update(['status' => 'failed', 'failure_reason' => 'No payout account on file for this author.']);

            return;
        }

        // Flutterwave is the only gateway currently registered/active (see
        // PaymentServiceProvider) - if that ever changes, whichever gateway is
        // first and actually implements SupportsPayouts is used.
        $gateway = collect($gateways->names())
            ->map(fn ($name) => $gateways->get($name))
            ->first(fn ($g) => $g instanceof SupportsPayouts);

        if (! $gateway) {
            return; // stays 'pending' - no payout-capable gateway registered
        }

        try {
            $transferId = $gateway->sendPayout($payout);
            $payout->update(['status' => 'processing', 'gateway_transfer_id' => $transferId]);
        } catch (\Throwable $e) {
            $payout->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
        }
    }
}
