<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\DocumentExpiryNotification;
use Illuminate\Console\Command;

class SendDocumentExpiryNotifications extends Command
{
    protected $signature = 'documents:send-expiry-notifications';

    protected $description = 'Send email notifications to users about expiring and expired documents';

    public function handle(): void
    {
        // TODO: Consider deduplication to avoid sending repeated notifications for the same documents
        $users = User::whereHas('documents', function ($query) {
            $query->expiringSoon();
        })->orWhereHas('documents', function ($query) {
            $query->expired()->notArchived();
        })->get();

        foreach ($users as $user) {
            $expiringSoon = $user->documents()->expiringSoon()->get();
            $expiredNotArchived = $user->documents()->expired()->notArchived()->get();

            if ($expiringSoon->isEmpty() && $expiredNotArchived->isEmpty()) {
                continue;
            }

            $user->notify(new DocumentExpiryNotification($expiringSoon, $expiredNotArchived));
        }

        $this->info("Sent expiry notifications to {$users->count()} user(s).");
    }
}
