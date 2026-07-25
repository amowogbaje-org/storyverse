<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Services\BadgeMetricResolver;
use Illuminate\Http\Request;

class BadgeController extends Controller
{
    public function __construct(private BadgeMetricResolver $resolver) {}

    public function index(Request $request)
    {
        $user = $this->currentUser($request);
        $badges = Badge::all();

        $earnedIds = $user
            ? $user->badges()->pluck('badge_id')->flip()
            : collect();

        return $this->ok($badges->map(function (Badge $b) use ($user, $earnedIds) {
            $earned = $earnedIds->has($b->id);

            // null means "we can't compute this one yet" (see BadgeMetricResolver's
            // docblock for which criteria_types still need a resolver) - the
            // frontend just hides the progress bar for those rather than showing
            // a misleading 0%.
            $current = $user && ! $earned ? $this->resolver->resolve($user, $b->criteria_type) : null;

            return [
                'id' => $b->id,
                'name' => $b->name,
                'slug' => $b->slug,
                'description' => $b->description,
                'icon_url' => $b->icon_url,
                'category' => $b->category,
                'tier' => $b->tier,
                'criteria_value' => $b->criteria_value,
                'earned' => $earned,
                'progress_current' => $earned ? $b->criteria_value : $current,
                'progress_percent' => $earned ? 100 : ($current === null ? null : min(100, (int) round($current / max($b->criteria_value, 1) * 100))),
            ];
        }));
    }

    public function mine(Request $request)
    {
        $user = $this->requireUser($request);

        $badges = $user->badges()->with('badge')->get()->map(fn ($ub) => [
            'id' => $ub->badge->id,
            'name' => $ub->badge->name,
            'tier' => $ub->badge->tier,
            'icon_url' => $ub->badge->icon_url,
            'earned_at' => $ub->earned_at,
        ]);

        return $this->ok($badges);
    }

    /**
     * The badges closest to being earned - what to show as "here's what's
     * next" (e.g. a profile/dashboard widget: "3 episodes to go for Bookworm").
     * Only includes badges whose criteria_type BadgeMetricResolver can
     * actually compute; unresolvable ones are silently left out rather than
     * shown with a fake/zero progress.
     */
    public function next(Request $request)
    {
        $user = $this->requireUser($request);
        $earnedIds = $user->badges()->pluck('badge_id');

        $candidates = Badge::whereNotIn('id', $earnedIds)->get()
            ->map(function (Badge $b) use ($user) {
                $current = $this->resolver->resolve($user, $b->criteria_type);

                if ($current === null) {
                    return null;
                }

                return [
                    'id' => $b->id,
                    'name' => $b->name,
                    'description' => $b->description,
                    'icon_url' => $b->icon_url,
                    'category' => $b->category,
                    'tier' => $b->tier,
                    'criteria_value' => $b->criteria_value,
                    'progress_current' => min($current, $b->criteria_value),
                    'progress_percent' => min(100, (int) round($current / max($b->criteria_value, 1) * 100)),
                    'remaining' => max(0, $b->criteria_value - $current),
                ];
            })
            ->filter()
            ->sortByDesc('progress_percent')
            ->values()
            ->take((int) $request->query('limit', 3));

        return $this->ok($candidates);
    }
}
