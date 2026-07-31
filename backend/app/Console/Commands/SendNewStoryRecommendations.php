<?php

namespace App\Console\Commands;

use App\Models\Story;
use App\Models\User;
use App\Notifications\NewStoryRecommendation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "New stories based on your interest, but picked randomly so it doesn't feel
 * like spam." Two separate anti-spam mechanisms working together:
 *
 * 1. A per-user cooldown (3 days) - whatever the very first qualifying match
 *    is for a given run, once we send it, that user is off the list for the
 *    next 3 days regardless of how many more new/matching stories show up.
 * 2. A random roll on top of that - even within the cooldown window, not
 *    every interested reader gets notified about every matching story, so it
 *    doesn't feel like an automatic "new story = ping everyone" bot.
 */
class SendNewStoryRecommendations extends Command
{
    protected $signature = 'app:send-new-story-recommendations {--chance=0.4 : Probability (0-1) an eligible reader gets notified}';

    protected $description = 'Notify readers about recently-published stories that match categories they\'ve engaged with';

    private const COOLDOWN_DAYS = 3;

    public function handle(): int
    {
        $chance = (float) $this->option('chance');

        $recentStories = Story::where('status', 'published')
            ->where('published_at', '>=', now()->subDay())
            ->where('published_at', '<', now())
            ->with('categories')
            ->get();

        $sent = 0;

        foreach ($recentStories as $story) {
            $categoryIds = $story->categories->pluck('id');

            if ($categoryIds->isEmpty()) {
                continue;
            }

            $candidates = $this->interestedReaders($story, $categoryIds);

            foreach ($candidates->shuffle() as $user) {
                $cooldownKey = "notif:recommend:{$user->id}";

                if (Cache::has($cooldownKey)) {
                    continue;
                }

                if ((mt_rand(1, 1000) / 1000) > $chance) {
                    continue;
                }

                $user->notify(new NewStoryRecommendation($story));
                Cache::put($cooldownKey, true, now()->addDays(self::COOLDOWN_DAYS));
                $sent++;
            }
        }

        $this->info("Sent {$sent} new-story recommendation(s).");

        return self::SUCCESS;
    }

    /**
     * Readers who've liked, bookmarked, or completed a story sharing any of
     * this story's categories before - "interest" - excluding anyone who's
     * already interacted with this exact story (they don't need a
     * recommendation for something they've already found). A story can
     * belong to more than one category, so this matches on any overlap
     * rather than a single equality check.
     */
    private function interestedReaders(Story $story, \Illuminate\Support\Collection $categoryIds)
    {
        $alreadyEngaged = DB::table('reading_progress')->where('story_id', $story->id)->pluck('user_id')
            ->merge(DB::table('story_likes')->where('story_id', $story->id)->pluck('user_id'))
            ->merge(DB::table('story_bookmarks')->where('story_id', $story->id)->pluck('user_id'))
            ->unique();

        $interestedIds = DB::table('story_likes')
            ->join('category_story', 'category_story.story_id', '=', 'story_likes.story_id')
            ->whereIn('category_story.category_id', $categoryIds)
            ->pluck('story_likes.user_id')
            ->merge(
                DB::table('story_bookmarks')
                    ->join('category_story', 'category_story.story_id', '=', 'story_bookmarks.story_id')
                    ->whereIn('category_story.category_id', $categoryIds)
                    ->pluck('story_bookmarks.user_id')
            )
            ->merge(
                DB::table('reading_progress')
                    ->join('category_story', 'category_story.story_id', '=', 'reading_progress.story_id')
                    ->whereIn('category_story.category_id', $categoryIds)
                    ->whereNotNull('reading_progress.completed_at')
                    ->pluck('reading_progress.user_id')
            )
            ->unique()
            ->diff($alreadyEngaged);

        return User::whereIn('id', $interestedIds)->whereNotNull('email_verified_at')->get();
    }
}
