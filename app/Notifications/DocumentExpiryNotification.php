<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class DocumentExpiryNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Collection $expiringSoon,
        protected Collection $expiredNotArchived,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Document Expiry Reminder')
            ->greeting("Hello {$notifiable->name},");

        if ($this->expiringSoon->isNotEmpty()) {
            $message->line('The following documents are expiring within the next 7 days:');

            foreach ($this->expiringSoon as $document) {
                $message->line("- {$document->name} (expires {$document->expires_at->format('d/m/Y')})");
            }
        }

        if ($this->expiredNotArchived->isNotEmpty()) {
            $message->line('The following documents have expired and are not yet archived:');

            foreach ($this->expiredNotArchived as $document) {
                $message->line("- {$document->name} (expired {$document->expires_at->format('d/m/Y')})");
            }
        }

        $message->line('Please log in to review and take action on these documents.');

        return $message;
    }
}
