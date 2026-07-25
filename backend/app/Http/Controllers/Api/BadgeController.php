<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use Illuminate\Http\Request;

class BadgeController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->currentUser($request);
        $badges = Badge::all();

        $earnedIds = $user
            ? $user->badges()->pluck('badge_id')->flip()
            : collect();

        return $this->ok($badges->map(fn (Badge $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'slug' => $b->slug,
            'description' => $b->description,
            'icon_url' => $b->icon_url,
            'category' => $b->category,
            'tier' => $b->tier,
            'earned' => $earnedIds->has($b->id),
        ]));
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
}
