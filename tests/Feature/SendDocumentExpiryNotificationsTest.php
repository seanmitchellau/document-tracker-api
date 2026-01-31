<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentExpiryNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendDocumentExpiryNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function testItSendsNotificationsToUsersWithExpiringSoonDocuments()
    {
        Notification::fake();

        $user = User::factory()->create();
        Document::factory()->for($user, 'owner')->create([
            'expires_at' => now()->addDays(3),
        ]);

        $this->artisan('documents:send-expiry-notifications')
            ->assertSuccessful();

        Notification::assertSentTo($user, DocumentExpiryNotification::class);
    }

    public function testItSendsNotificationsToUsersWithExpiredNotArchivedDocuments()
    {
        Notification::fake();

        $user = User::factory()->create();
        Document::factory()->for($user, 'owner')->create([
            'expires_at' => now()->subDays(5),
        ]);

        $this->artisan('documents:send-expiry-notifications')
            ->assertSuccessful();

        Notification::assertSentTo($user, DocumentExpiryNotification::class);
    }

    public function testItDoesNotSendNotificationsWhenNoDocumentsQualify()
    {
        Notification::fake();

        $user = User::factory()->create();

        // Document expiring in 30 days - not soon
        Document::factory()->for($user, 'owner')->create([
            'expires_at' => now()->addDays(30),
        ]);

        // Expired but already archived
        Document::factory()->for($user, 'owner')->create([
            'expires_at' => now()->subDays(10),
            'archived_at' => now()->subDays(2),
        ]);

        $this->artisan('documents:send-expiry-notifications')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }
}
