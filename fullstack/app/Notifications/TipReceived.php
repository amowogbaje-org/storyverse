<?php

namespace App\Notifications;

use App\Models\Tip;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TipReceived extends Notification
{
    use Queueable;

    public function __construct(public Tip $tip) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    private function body(): string
    {
        $amount = number_format((float) $this->tip->amount, 2);
        $name = $this->tip->tipper?->display_name ?? 'A reader';

        return "{$name} sent you {$this->tip->currency} {$amount} on \"{$this->tip->penName->display_name}\"".
            ($this->tip->message ? ": \"{$this->tip->message}\"" : '.');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'tip_received',
            'title' => 'You got a tip! 💝',
            'body' => $this->body(),
            'url' => '/admin/earnings',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You received a tip on Storyverse!')
            ->greeting('Someone appreciates your writing!')
            ->line($this->body())
            ->action('See your earnings', config('app.url').'/admin/earnings');
    }
}
