<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ReadingProgress;
use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "there should be a session that shows their earnings" — per the project brief.
 * There's no per-story payment attribution in this schema (subscriptions are
 * platform-wide, not tied to a specific story), so this estimates each author's
 * share of subscription revenue by their share of completed reads in the period.
 * That's a policy choice, not a contractual figure — flagged explicitly in the
 * response so nobody mistakes it for an exact payout.
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

        $myCompletedReads = ReadingProgress::whereIn('story_id', $storyIds)
            ->where('completed_at', '>=', $since)
            ->count();

        $platformCompletedReads = ReadingProgress::where('completed_at', '>=', $since)->count();

        $share = $platformCompletedReads > 0 ? $myCompletedReads / $platformCompletedReads : 0.0;

        $platformRevenue = Payment::where('status', 'success')
            ->where('created_at', '>=', $since)
            ->select('currency', DB::raw('sum(amount) as total'))
            ->groupBy('currency')
            ->pluck('total', 'currency');

        return $this->ok([
            'period_days' => $days,
            'my_completed_reads' => $myCompletedReads,
            'platform_completed_reads' => $platformCompletedReads,
            'revenue_share_percent' => round($share * 100, 2),
            'estimated_earnings_by_currency' => $platformRevenue->map(fn ($total) => round($total * $share, 2)),
            'platform_revenue_by_currency' => $platformRevenue,
            'note' => 'Estimated from a completed-reads revenue-share model, not a contractual payout figure.',
        ]);
    }
}
