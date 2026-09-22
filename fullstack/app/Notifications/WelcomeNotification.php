<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired once, right after a brand-new account finishes OTP verification
 * (see AuthController::verifyOtp). Not sent on every login/resend - only
 * the first time email_verified_at transitions from null to set.
 */
class WelcomeNotification extends Notification
{
    use Queueable;

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to Storyverse!')
            ->greeting("Welcome, {$notifiable->name}!")
            ->line("Your email is verified and your account is ready to go.")
            ->line('Start reading, save your favorite stories, and earn badges as you go.')
            ->action('Browse stories', config('app.url').'/browse')
            ->line('Glad to have you with us.');
    }
}
