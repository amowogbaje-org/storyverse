<?php

namespace App\Notifications;

use App\Channels\WebPushChannel;
use App\Models\Episode;
use App\Models\Story;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ContinueReading extends Notification
{
    use Queueable;

    private const LINES = [
        "You were % through \"{title}\" - episode {episode} is waiting.",
        "Cliffhanger alert: you left off partway through \"{title}\".",
        "Pick back up on \"{title}\" - you were % of the way through episode {episode}.",
    ];

    private string $body;

    public function __construct(public Story $story, public Episode $episode, public int $percent)
    {
        $line = self::LINES[array_rand(self::LINES)];
        $this->body = str_replace(
            ['%', '{title}', '{episode}'],
            [$percent.'%', $story->title, $episode->episode_number],
            $line
        );
    }

    public function via($notifiable): array
    {
        if (! (($notifiable->notification_preferences ?? [])['continue_reading_push'] ?? true)) {
            return [];
        }

        return ['database', WebPushChannel::class];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'continue_reading',
            'title' => 'Continue reading',
            'body' => $this->body,
            'url' => "/stories/{$this->story->slug}/episodes/{$this->episode->episode_number}",
            'story_id' => $this->story->id,
            'cover_image_url' => $this->story->cover_image_url,
        ];
    }

    public function toWebPush($notifiable): array
    {
        return [
            'title' => 'Continue reading 📚',
            'body' => $this->body,
            'url' => "/stories/{$this->story->slug}/episodes/{$this->episode->episode_number}",
            'icon' => $this->story->cover_image_url,
        ];
    }
}
