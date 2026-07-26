<?php

namespace App\Notifications;

use App\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReferralMilestoneReached extends Notification
{
    use Queueable;

    public function __construct(public int $verifiedCount, public int $freeDaysGranted) {}

    public function via($notifiable): array
    {
        return ['database', WebPushChannel::class, 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'referral_milestone',
            'title' => "You've earned a free month! 🎉",
            'body' => "{$this->verifiedCount} verified referrals and counting - {$this->freeDaysGranted} days of premium just landed on your account.",
            'url' => '/referrals',
        ];
    }

    public function toWebPush($notifiable): array
    {
        return [
            'title' => "You've earned a free month! 🎉",
            'body' => "{$this->verifiedCount} verified referrals - {$this->freeDaysGranted} days of premium just got added.",
            'url' => '/referrals',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You've earned a free month of Storyverse Premium!")
            ->greeting('Nicely done!')
            ->line("You've now referred {$this->verifiedCount} verified readers to Storyverse.")
            ->line("We've added {$this->freeDaysGranted} days of premium access to your account, starting today.")
            ->line('Keep sharing your link - every 10 verified referrals earns another free month.')
            ->action('See your referral progress', url('/referrals'));
    }
}
