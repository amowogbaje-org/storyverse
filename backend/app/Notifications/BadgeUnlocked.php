<?php

namespace App\Notifications;

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
        // In-app always fires. Email also fires for every tier now - it's the
        // only thing that reliably tells the user they unlocked something,
        // since the in-app badge state is easy to miss. Still opt-outable.
        $channels = ['database'];

        $wantsEmail = $notifiable->notification_preferences['badge_emails'] ?? true;

        if ($wantsEmail) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'badge_unlocked',
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
            ->action('View your badges', url('/profile/badges'));
    }
}
