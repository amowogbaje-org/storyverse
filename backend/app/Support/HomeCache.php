<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Caches the shared (non-per-user) part of the homepage story lists. Uses only
 * the plain Cache facade - no redis-only features like tags - so it behaves
 * identically whether CACHE_STORE is redis (Docker/cloud) or file/database
 * (cPanel, where redis isn't available; see .env.cpanel.example).
 *
 * Per-user overlay (is_liked_by_user, is_bookmarked_by_user, reader_progress_percent)
 * is deliberately NOT part of the cached payload - it's computed fresh on every
 * request from StoryCardPresenter's batched maps, so two different readers never
 * see each other's like/bookmark state.
 */
class HomeCache
{
    private const TTL_MINUTES = 5;

    public const NEW_RELEASES_KEY = 'home:new-releases';
    public const POPULAR_KEY = 'home:popular';
    public const GENRES_KEY = 'home:genres';

    public static function remember(string $key, \Closure $callback)
    {
        return Cache::remember($key, now()->addMinutes(self::TTL_MINUTES), $callback);
    }

    /**
     * Call after anything that changes which stories should appear in the
     * homepage lists or how they're ordered/labelled: publish, unpublish,
     * delete, or an edit to title/cover/access_type. Deliberately NOT called on
     * every like/view increment - those are left to expire on the TTL above,
     * since busting the cache on every single view would defeat the point of
     * caching a high-traffic page in the first place.
     */
    public static function forgetHomepage(): void
    {
        Cache::forget(self::NEW_RELEASES_KEY);
        Cache::forget(self::POPULAR_KEY);
        Cache::forget(self::GENRES_KEY);
    }
}
