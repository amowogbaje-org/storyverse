<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Genre;
use App\Models\Story;
use App\Support\GenreCategoryMatcher;
use App\Support\StoryTemplateParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bulk manuscript import: upload a Markdown file -> parse + validate ->
 * preview with auto-matched categories/genres -> confirm -> every episode
 * gets created as a draft in one go, exactly as if the author had pasted
 * each one in individually through EpisodeManagementController (same
 * content/raw_content/status fields), so the existing styling pipeline
 * (StyleEpisodes) picks all of them up normally afterward.
 *
 * Deliberately two steps (preview, then import) rather than one: preview
 * never writes to the database, so an author can upload, see exactly what
 * would happen - matched genres, episode count, any validation problems -
 * and adjust categories/genres or bail out with zero risk of a half-created
 * story sitting in their dashboard.
 */
class StoryImportController extends Controller
{
    // Every bulk-imported story starts with this until the author uploads a
    // real cover - see frontend/public/images/placeholder-cover.svg. A
    // shared placeholder rather than a per-story generated one: no image
    // library involved, and "quick publishing" means getting episodes in
    // fast, not blocking on cover art.
    public const PLACEHOLDER_COVER_URL = '/images/placeholder-cover.svg';

    public function template()
    {
        $path = resource_path('templates/bulk-import-template.md');

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="storyverse-bulk-import-template.md"',
        ]);
    }

    /**
     * Parses the upload and returns a full preview - matched category/genre
     * suggestions, unmatched genre tokens, every episode with its title/word
     * count, and any structural errors/warnings. Nothing is persisted here.
     */
    public function preview(Request $request)
    {
        $user = $this->requireUser($request);

        $request->validate([
            'file' => ['required', 'file', 'max:10240'], // 10MB
            'pen_name_id' => ['required', 'exists:pen_names,id'],
        ]);

        $this->assertOwnsPenName($user, (int) $request->input('pen_name_id'));

        $extension = strtolower($request->file('file')->getClientOriginalExtension());
        if (! in_array($extension, ['md', 'markdown', 'txt'], true)) {
            return $this->error('unsupported_file_type', 'Please upload a .md, .markdown, or .txt file.', 422);
        }

        $markdown = file_get_contents($request->file('file')->getRealPath());
        $parsed = (new StoryTemplateParser)->parse($markdown);

        $categories = Category::all();
        $genres = Genre::all();
        $matched = GenreCategoryMatcher::match($parsed['genre_tokens'], $categories, $genres);

        // Best-effort nudge toward "this looks like it continues a story you
        // already have" (e.g. importing a Season 3 file for an existing
        // Season 1-2 story) - never auto-selected, just offered, since a
        // manuscript's own title (or lack of one) is too unreliable to act
        // on without the author confirming it.
        $existingStories = Story::where('pen_name_id', $request->input('pen_name_id'))
            ->get(['id', 'title', 'slug'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'slug' => $s->slug,
                'title_similarity' => $parsed['title']
                    ? $this->titleSimilarity($parsed['title'], $s->title)
                    : 0,
            ])
            ->sortByDesc('title_similarity')
            ->values();

        return $this->ok([
            'valid' => $parsed['valid'],
            'errors' => $parsed['errors'],
            'warnings' => $parsed['warnings'],
            'title' => $parsed['title'],
            'description' => $parsed['description'],
            'episode_count' => $parsed['episode_count'],
            'total_word_count' => $parsed['total_word_count'],
            'episodes' => collect($parsed['episodes'])->map(fn ($e) => [
                'number' => $e['number'],
                'title' => $e['title'],
                'content' => $e['content'],
                'word_count' => $e['word_count'],
            ]),
            'genre_tokens' => $parsed['genre_tokens'],
            'matched_category_ids' => $matched['category_ids'],
            'matched_genre_ids' => $matched['genre_ids'],
            'unmatched_genre_tokens' => $matched['unmatched'],
            'suggested_existing_stories' => $existingStories,
        ]);
    }

    /**
     * Actually creates the story (or adds to an existing one) and every
     * episode, in one transaction. Expects the same shape preview()
     * returned, after the author has reviewed/adjusted it client-side -
     * this endpoint re-validates but trusts the episode content it's given
     * rather than re-parsing the original file, since the author may have
     * hand-edited a title or description in the review screen.
     */
    public function import(Request $request)
    {
        $user = $this->requireUser($request);

        $data = $request->validate([
            'pen_name_id' => ['required', 'exists:pen_names,id'],
            'story_id' => ['nullable', 'exists:stories,id'],
            'title' => ['required_without:story_id', 'nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_ids' => ['required_without:story_id', 'array'],
            'category_ids.*' => ['exists:categories,id'],
            'genre_ids' => ['sometimes', 'array'],
            'genre_ids.*' => ['exists:genres,id'],
            'new_genre_names' => ['sometimes', 'array'],
            'new_genre_names.*' => ['string', 'max:100'],
            'episodes' => ['required', 'array', 'min:1'],
            'episodes.*.title' => ['required', 'string', 'max:255'],
            'episodes.*.content' => ['required', 'string'],
        ]);

        $this->assertOwnsPenName($user, (int) $data['pen_name_id']);

        $result = DB::transaction(function () use ($data, $user) {
            if (! empty($data['story_id'])) {
                $story = Story::findOrFail($data['story_id']);
                if ($user->role !== 'admin' && $story->pen_name_id !== (int) $data['pen_name_id']) {
                    abort(response()->json(['error' => ['code' => 'forbidden', 'message' => 'You do not own this story.']], 403));
                }
            } else {
                $story = Story::create([
                    'pen_name_id' => $data['pen_name_id'],
                    'title' => $data['title'],
                    'slug' => $this->uniqueSlug($data['title']),
                    'description' => $data['description'] ?: 'Description coming soon.',
                    'cover_image_url' => self::PLACEHOLDER_COVER_URL,
                    'status' => 'draft',
                    'access_type' => 'free',
                ]);
            }

            $genreIds = $data['genre_ids'] ?? [];
            foreach ($data['new_genre_names'] ?? [] as $name) {
                $name = trim($name);
                if ($name === '') {
                    continue;
                }
                $genre = Genre::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
                $genreIds[] = $genre->id;
            }

            if (! empty($data['category_ids'])) {
                $story->categories()->syncWithoutDetaching($data['category_ids']);
            }
            if (! empty($genreIds)) {
                $story->genres()->syncWithoutDetaching(array_unique($genreIds));
            }

            $nextNumber = ($story->episodes()->max('episode_number') ?? 0) + 1;
            $createdCount = 0;

            foreach ($data['episodes'] as $ep) {
                // Mirrors EpisodeManagementController::store exactly - same
                // field set, same draft status, same content===raw_content
                // starting point - so StyleEpisodes treats a bulk-imported
                // episode no differently than one typed in by hand.
                $story->episodes()->create([
                    'title' => $ep['title'],
                    'content' => $ep['content'],
                    'raw_content' => $ep['content'],
                    'raw_content_updated_at' => now(),
                    'styled_at' => null,
                    'styling_attempts' => 0,
                    'episode_number' => $nextNumber,
                    'word_count' => str_word_count(strip_tags($ep['content'])),
                    'status' => 'draft',
                ]);
                $nextNumber++;
                $createdCount++;
            }

            $story->increment('episodes_count', $createdCount);

            return [$story, $createdCount];
        });

        [$story, $createdCount] = $result;

        return $this->ok(
            [
                'story' => $story->fresh(['penName', 'categories', 'genres', 'episodes']),
                // Distinct from the story's total episode count, which
                // includes anything already there when importing into an
                // existing story - the confirmation screen needs "how many
                // did this import just add", not "how many exist now".
                'episodes_created' => $createdCount,
            ],
            201
        );
    }

    private function assertOwnsPenName($user, int $penNameId): void
    {
        if ($user->role === 'admin') {
            return;
        }

        if (! $user->penNames()->where('id', $penNameId)->exists()) {
            abort(response()->json(['error' => ['code' => 'forbidden', 'message' => 'You do not own this pen name.']], 403));
        }
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 1;

        while (Story::where('slug', $slug)->exists()) {
            $slug = "{$base}-".(++$i);
        }

        return $slug;
    }

    /** Crude but dependency-free title-closeness score, 0-1, for the "did you mean this existing story?" nudge. */
    private function titleSimilarity(string $a, string $b): float
    {
        similar_text(strtolower($a), strtolower($b), $percent);

        return round($percent / 100, 2);
    }
}
