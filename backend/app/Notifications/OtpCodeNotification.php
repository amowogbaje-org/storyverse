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
     * @param string $purpose "verify" (email verification during signup), "login" (passwordless
     *   sign-in), or "password_reset".
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
        $intro = match ($this->purpose) {
            'login' => 'Use this code to sign in to Storyverse:',
            'password_reset' => 'Use this code to reset your Storyverse password:',
            default => 'Use this code to verify your email and finish creating your Storyverse account:',
        };

        $subject = $this->purpose === 'password_reset'
            ? 'Reset your Storyverse password'
            : 'Your Storyverse verification code';

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting('Hi there,')
            ->line($intro)
            ->line("## {$this->formattedCode()}")
            ->line('This code expires in 1 minute.');

        if ($this->purpose === 'password_reset') {
            $mail->line("If you didn't request a password reset, you can safely ignore this email - your password won't change unless this code is used.");
        } else {
            $mail->line("If you didn't request this, you can safely ignore this email.");
        }

        return $mail;
    }

    private function formattedCode(): string
    {
        // Spaced out (e.g. "1 2 3 4 5 6") so it doesn't get auto-linkified/mangled by mail clients.
        return implode(' ', str_split($this->code));
    }
}
