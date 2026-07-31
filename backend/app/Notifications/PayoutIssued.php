<?php

namespace App\Notifications;

use App\Channels\WebPushChannel;
use App\Models\Payout;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutIssued extends Notification
{
    use Queueable;

    public function __construct(public Payout $payout) {}

    public function via($notifiable): array
    {
        return ['database', WebPushChannel::class, 'mail'];
    }

    private function body(): string
    {
        $amount = number_format((float) $this->payout->total_amount, 2);
        $month = $this->payout->period_start->format('F Y');

        return "Your {$month} payout of {$this->payout->currency} {$amount} has been generated.";
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'payout_issued',
            'title' => 'Payout generated',
            'body' => $this->body(),
            'url' => '/admin/earnings',
        ];
    }

    public function toWebPush($notifiable): array
    {
        return [
            'title' => 'Payout generated 💰',
            'body' => $this->body(),
            'url' => '/admin/earnings',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $hasAccount = $this->payout->payout_account_number;

        $mail = (new MailMessage)
            ->subject('Your Storyverse payout is ready')
            ->greeting('Nice work this month!')
            ->line($this->body());

        if (! $hasAccount) {
            $mail->line("You haven't added a payout account yet - add your bank details in Settings so this can actually be sent to you.");
        } elseif (! config('payouts.auto_send_enabled')) {
            $mail->line("This will be sent to your account on file ({$this->payout->payout_bank_name}, ending in ".substr($this->payout->payout_account_number, -4).') by the team.');
        } else {
            $mail->line("It's on its way to your account on file ({$this->payout->payout_bank_name}, ending in ".substr($this->payout->payout_account_number, -4).').');
        }

        return $mail->action('See your earnings', config('app.frontend_url').'/admin/earnings');
    }
}
