<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PenName;
use App\Models\Story;
use App\Support\StoryCardPresenter;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private \App\Services\AiSearchService $aiSearch) {}

    public function native(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $user = $this->currentUser($request);

        if ($q === '') {
            return $this->ok(['stories' => [], 'authors' => []]);
        }

        // Native (non-AI) search: simple LIKE matching for now. Swap this query for a
        // Scout::search() call once Meilisearch indexing is wired up (composer.json
        // already has laravel/scout + the meilisearch client ready for that).
        $stories = Story::where('status', 'published')
            ->where(function ($query) use ($q) {
                $query->where('title', 'ilike', "%{$q}%")
                    ->orWhere('description', 'ilike', "%{$q}%");
            })
            ->with(['penName', 'category'])
            ->limit(20)
            ->get();

        $authors = PenName::where('display_name', 'ilike', "%{$q}%")
            ->limit(10)
            ->get(['display_name', 'slug']);

        if ($user) {
            \App\Models\UserActivityEvent::create([
                'user_id' => $user->id,
                'event_type' => 'search_performed',
                'metadata' => ['query' => $q, 'result_count' => $stories->count()],
                'created_at' => now(),
            ]);
            \App\Events\UserActivityLogged::dispatchSync($user->id, 'search_performed', ['query' => $q]);
        }

        $progress = StoryCardPresenter::progressMap($user, $stories->pluck('id'));

        return $this->ok([
            'stories' => $stories->map(fn (Story $s) => StoryCardPresenter::card($s, $user, $progress)),
            'authors' => $authors,
        ]);
    }

    public function ai(Request $request)
    {
        $data = $request->validate(['query' => ['required', 'string', 'max:500']]);
        $user = $this->currentUser($request);

        if ($user) {
            \App\Models\UserActivityEvent::create([
                'user_id' => $user->id,
                'event_type' => 'ai_search_used',
                'metadata' => ['query' => $data['query']],
                'created_at' => now(),
            ]);
            \App\Events\UserActivityLogged::dispatchSync($user->id, 'ai_search_used', ['query' => $data['query']]);
        }

        $ai = $this->aiSearch->search($data['query']);

        if ($ai['degraded'] || empty($ai['results'])) {
            // OpenAI unavailable, unconfigured, or found nothing - native search never
            // goes down because of it. The frontend can't tell the difference from the
            // response shape alone, which is intentional: it always gets *a* result set.
            return $this->fallbackToNativeSearch($data['query']);
        }

        $slugs = collect($ai['results'])->pluck('slug');
        $reasons = collect($ai['results'])->pluck('reason', 'slug');

        $stories = Story::where('status', 'published')
            ->whereIn('slug', $slugs)
            ->with(['penName', 'category'])
            ->get()
            ->sortBy(fn (Story $s) => $slugs->search($s->slug))
            ->values();

        $progress = StoryCardPresenter::progressMap($user, $stories->pluck('id'));

        return $this->ok([
            'stories' => $stories->map(fn (Story $s) => [
                ...StoryCardPresenter::card($s, $user, $progress),
                'ai_reason' => $reasons[$s->slug] ?? null,
            ]),
            'source' => 'ai',
        ]);
    }

    private function fallbackToNativeSearch(string $query)
    {
        $user = null; // this path is reached without needing $request again
        $stories = Story::where('status', 'published')
            ->where(function ($q) use ($query) {
                $q->where('title', 'ilike', "%{$query}%")->orWhere('description', 'ilike', "%{$query}%");
            })
            ->with(['penName', 'category'])
            ->limit(10)
            ->get();

        return $this->ok([
            'stories' => $stories->map(fn (Story $s) => StoryCardPresenter::card($s, $user, collect())),
            'source' => 'native_fallback',
        ]);
    }
}
