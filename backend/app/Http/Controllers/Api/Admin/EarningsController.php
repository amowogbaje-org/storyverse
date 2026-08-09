<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PenName;
use App\Models\Story;
use App\Models\StoryPurchase;
use App\Models\Tip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "there should be a session that shows their earnings" - per the project brief.
 *
 * This used to estimate each author's share of subscription revenue by their
 * share of completed reads platform-wide, since subscription revenue
 * couldn't be tied to any one story. Now that stories are sold individually
 * (see story_prices/StoryPurchase), a purchase already IS attributed to one
 * specific story, so this reports the real number for the period rather than
 * an estimate - see GenerateMonthlyPayouts for the same figures used to
 * actually generate a payout.
 */
class EarningsController extends Controller
{
    public function mine(Request $request)
    {
        $user = $this->requireUser($request);
        $days = (int) $request->query('days', 30);
        $since = now()->subDays($days);

        $storyIds = $user->role === 'admin'
            ? Story::pluck('id')
            : Story::whereIn('pen_name_id', $user->penNames()->pluck('id'))->pluck('id');

        $grossSalesByCurrency = StoryPurchase::whereIn('story_id', $storyIds)
            ->where('status', 'success')
            ->where('created_at', '>=', $since)
            ->select('currency', DB::raw('sum(amount) as total'))
            ->groupBy('currency')
            ->pluck('total', 'currency');

        $penNameIds = $user->role === 'admin' ? PenName::pluck('id') : $user->penNames()->pluck('id');

        $tipsByCurrency = Tip::whereIn('pen_name_id', $penNameIds)
            ->where('status', 'success')
            ->where('created_at', '>=', $since)
            ->select('currency', DB::raw('sum(amount) as total'))
            ->groupBy('currency')
            ->pluck('total', 'currency');

        $sharePercent = (float) config('payouts.author_share_percentage');

        $currencies = $grossSalesByCurrency->keys()->merge($tipsByCurrency->keys())->unique();
        $totalsByCurrency = $currencies->mapWithKeys(function ($currency) use ($grossSalesByCurrency, $tipsByCurrency, $sharePercent) {
            $yourShareOfSales = round(($grossSalesByCurrency[$currency] ?? 0) * $sharePercent, 2);
            $tips = round((float) ($tipsByCurrency[$currency] ?? 0), 2);

            return [$currency => round($yourShareOfSales + $tips, 2)];
        });

        return $this->ok([
            'period_days' => $days,
            'author_share_percent' => round($sharePercent * 100, 2),
            'gross_story_sales_by_currency' => $grossSalesByCurrency,
            'tips_by_currency' => $tipsByCurrency,
            'your_earnings_by_currency' => $totalsByCurrency,
            'note' => 'Your share of story sales plus tips for this period. Story purchases are attributed to your stories exactly, not estimated.',
        ]);
    }
}
