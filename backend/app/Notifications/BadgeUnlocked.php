<?php

namespace App\Notifications;

use App\Channels\WebPushChannel;
use App\Models\Badge;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BadgeUnlocked extends Notification
{
    use Queueable;

    public function __construct(public Badge $badge) {}

    public function via($notifiable): array
    {
        // In-app always fires - it's the record of having earned it, not really
        // an interruption. Push and email are each separately opt-outable.
        $channels = ['database'];

        $prefs = $notifiable->notification_preferences ?? [];

        if ($prefs['badge_push'] ?? true) {
            $channels[] = WebPushChannel::class;
        }

        if ($prefs['badge_emails'] ?? true) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toWebPush($notifiable): array
    {
        return [
            'title' => "🏅 You earned \"{$this->badge->name}\"!",
            'body' => $this->badge->description,
            'url' => '/badges',
        ];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'badge_unlocked',
            'title' => "You earned \"{$this->badge->name}\"!",
            'body' => $this->badge->description,
            'url' => '/badges',
            'badge_id' => $this->badge->id,
            'badge_name' => $this->badge->name,
            'tier' => $this->badge->tier,
            'icon_url' => $this->badge->icon_url,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You earned the \"{$this->badge->name}\" badge!")
            ->line("Congratulations - you've unlocked the {$this->badge->tier} badge \"{$this->badge->name}\".")
            ->line($this->badge->description)
            ->action('View your badges', url('/badges'));
    }
}
