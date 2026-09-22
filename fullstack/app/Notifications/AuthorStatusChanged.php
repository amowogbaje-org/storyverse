<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Covers all three admin decisions on a reader's author status:
 * 'granted' (request approved, or an admin promoted them directly),
 * 'rejected' (request declined - they can ask again later), and
 * 'revoked' (an existing author was moved back to reader). In-app only -
 * this is a status update, not urgent enough to justify an email/push
 * interruption the way a payout or a tip is.
 */
class AuthorStatusChanged extends Notification
{
    use Queueable;

    public function __construct(public string $outcome) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return match ($this->outcome) {
            'granted' => [
                'type' => 'author_status_changed',
                'title' => "You're now an author!",
                'body' => 'Your author request was approved. Head to Author Studio to set up a pen name and publish.',
                'url' => '/studio',
            ],
            'rejected' => [
                'type' => 'author_status_changed',
                'title' => 'Your author request was declined',
                'body' => "It wasn't approved this time. You're welcome to request again later.",
                'url' => '/profile',
            ],
            'revoked' => [
                'type' => 'author_status_changed',
                'title' => 'Your author access was removed',
                'body' => 'Your account has been moved back to a reader account.',
                'url' => '/profile',
            ],
        };
    }
}
