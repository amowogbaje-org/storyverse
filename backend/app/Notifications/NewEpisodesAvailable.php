<?php

namespace App\Notifications;

use App\Channels\WebPushChannel;
use App\Models\Story;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * One notification per story per day, however many new episodes actually
 * went up in that window (see SendNewEpisodeDigest) - deliberately never
 * fired per-episode, so an author publishing several episodes in one day
 * doesn't turn into several separate pings for the same reader.
 */
class NewEpisodesAvailable extends Notification
{
    use Queueable;

    private string $body;

    public function __construct(public Story $story, public int $newEpisodeCount)
    {
        $this->body = $newEpisodeCount === 1
            ? "A new episode of \"{$story->title}\" just went up."
            : "{$newEpisodeCount} new episodes of \"{$story->title}\" just went up.";
    }

    public function via($notifiable): array
    {
        if (! (($notifiable->notification_preferences ?? [])['new_episode_push'] ?? true)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'new_episodes_available',
            'title' => 'New episodes are up',
            'body' => $this->body,
            'url' => "/stories/{$this->story->slug}",
            'story_id' => $this->story->id,
            'cover_image_url' => $this->story->cover_image_url,
        ];
    }

    public function toWebPush($notifiable): array
    {
        return [
            'title' => 'New episodes are up 🆕',
            'body' => $this->body,
            'url' => "/stories/{$this->story->slug}",
            'image' => $this->story->cover_image_url,
        ];
    }
}
