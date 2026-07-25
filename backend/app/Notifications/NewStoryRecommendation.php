<?php

namespace App\Notifications;

use App\Channels\WebPushChannel;
use App\Models\Story;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewStoryRecommendation extends Notification
{
    use Queueable;

    public function __construct(public Story $story) {}

    public function via($notifiable): array
    {
        if (! (($notifiable->notification_preferences ?? [])['new_story_push'] ?? true)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'new_story_recommendation',
            'title' => 'New story you might like',
            'body' => "\"{$this->story->title}\" just went up in {$this->story->category?->name}.",
            'url' => "/stories/{$this->story->slug}",
            'story_id' => $this->story->id,
            'cover_image_url' => $this->story->cover_image_url,
        ];
    }

    public function toWebPush($notifiable): array
    {
        return [
            'title' => 'New story you might like 📖',
            'body' => "\"{$this->story->title}\" just went up in {$this->story->category?->name}.",
            'url' => "/stories/{$this->story->slug}",
            'icon' => $this->story->cover_image_url,
        ];
    }
}
