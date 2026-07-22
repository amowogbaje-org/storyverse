<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpCodeNotification extends Notification
{
    use Queueable;

    /**
     * @param string $code The plaintext 6-digit code (only the hash is persisted, this is what gets emailed).
     * @param string $purpose "verify" (email verification during signup) or "login" (passwordless sign-in).
     */
    public function __construct(
        private readonly string $code,
        private readonly string $purpose = 'verify',
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $intro = $this->purpose === 'login'
            ? 'Use this code to sign in to Storyverse:'
            : 'Use this code to verify your email and finish creating your Storyverse account:';

        return (new MailMessage)
            ->subject('Your Storyverse verification code')
            ->greeting('Hi there,')
            ->line($intro)
            ->line("## {$this->formattedCode()}")
            ->line('This code expires in 10 minutes.')
            ->line("If you didn't request this, you can safely ignore this email.");
    }

    private function formattedCode(): string
    {
        // Spaced out (e.g. "1 2 3 4 5 6") so it doesn't get auto-linkified/mangled by mail clients.
        return implode(' ', str_split($this->code));
    }
}
