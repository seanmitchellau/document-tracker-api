<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentsTest extends TestCase
{
    use RefreshDatabase;

    // -- Index / Listing --

    public function testItCanListDocuments()
    {
        $user = User::factory()
            ->has(Document::factory()->count(5))
            ->create();

        $this->actingAs($user);

        $this->getJson('/documents')
            ->assertSuccessful()
            ->assertJsonCount(5, 'data');
    }

    public function testItOnlyListsDocumentsBelongingToTheAuthenticatedUser()
    {
        $user = User::factory()->has(Document::factory()->count(3))->create();
        $otherUser = User::factory()->has(Document::factory()->count(4))->create();

        $this->actingAs($user);

        $this->getJson('/documents')
            ->assertSuccessful()
            ->assertJsonCount(3, 'data');
    }

    public function testItCanFilterDocumentsExpiringSoon()
    {
        $user = User::factory()->create();

        // Expiring in 3 days - should appear
        Document::factory()->for($user, 'owner')->create([
            'expires_at' => now()->addDays(3),
        ]);

        // Expiring in 14 days - should not appear
        Document::factory()->for($user, 'owner')->create([
            'expires_at' => now()->addDays(14),
        ]);

        // Already expired - should not appear
        Document::factory()->for($user, 'owner')->create([
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($user);

        $this->getJson('/documents?filter=expiring_soon')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data');
    }

    public function testItCanFilterExpiredNotArchivedDocuments()
    {
        $user = User::factory()->create();

        // Expired, not archived - should appear
        Document::factory()->for($user, 'owner')->create([
            'expires_at' => now()->subDays(5),
        ]);

        // Expired and archived - should not appear
        Document::factory()->for($user, 'owner')->create([
            'expires_at' => now()->subDays(10),
            'archived_at' => now()->subDays(2),
        ]);

        // Not yet expired - should not appear
        Document::factory()->for($user, 'owner')->create([
            'expires_at' => now()->addDays(30),
        ]);

        $this->actingAs($user);

        $this->getJson('/documents?filter=expired')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data');
    }

    public function testItCanSortDocumentsByExpiryDate()
    {
        $user = User::factory()->create();

        $soonest = Document::factory()->for($user, 'owner')->create([
            'expires_at' => now()->addDays(1),
        ]);
        $latest = Document::factory()->for($user, 'owner')->create([
            'expires_at' => now()->addDays(30),
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/documents?sort=expires_at')
            ->assertSuccessful();

        $this->assertEquals($soonest->id, $response->json('data.0.id'));
        $this->assertEquals($latest->id, $response->json('data.1.id'));

        $response = $this->getJson('/documents?sort=-expires_at')
            ->assertSuccessful();

        $this->assertEquals($latest->id, $response->json('data.0.id'));
        $this->assertEquals($soonest->id, $response->json('data.1.id'));
    }

    // -- Show --

    public function testItCanShowADocument()
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user, 'owner')->create();

        $this->actingAs($user);

        $this->getJson("/documents/{$document->id}")
            ->assertSuccessful()
            ->assertJsonPath('data.id', $document->id)
            ->assertJsonPath('data.name', $document->name);
    }

    public function testItForbidsViewingAnotherUsersDocument()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $document = Document::factory()->for($owner, 'owner')->create();

        $this->actingAs($otherUser);

        $this->getJson("/documents/{$document->id}")
            ->assertForbidden();
    }

    // -- Store --

    public function testItCanStoreADocument()
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->create('certificate.pdf', 100, 'application/pdf');

        $this->postJson('/documents', [
            'name' => 'Working With Children Check',
            'file' => $file,
            'expires_at' => now()->addYear()->toDateString(),
        ])->assertSuccessful()
            ->assertJsonPath('data.name', 'Working With Children Check');

        $this->assertDatabaseHas('documents', [
            'name' => 'Working With Children Check',
            'owner_id' => $user->id,
        ]);

        Storage::disk('local')->assertExists('documents/' . $file->hashName());
    }

    public function testItRequiresNameFileAndExpiryToStoreADocument()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/documents', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'file', 'expires_at']);
    }

    public function testItRejectsNonPdfFileUploads()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->create('spreadsheet.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->postJson('/documents', [
            'name' => 'Not a PDF',
            'file' => $file,
            'expires_at' => now()->addWeek()->toDateString(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    // -- Update / Rename --

    public function testItCanRenameADocument()
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user, 'owner')->create([
            'name' => 'Old Name',
        ]);

        $this->actingAs($user);

        $this->patchJson("/documents/{$document->id}", [
            'name' => 'New Name',
        ])->assertSuccessful()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'name' => 'New Name',
        ]);
    }

    public function testItForbidsRenamingAnotherUsersDocument()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $document = Document::factory()->for($owner, 'owner')->create();

        $this->actingAs($otherUser);

        $this->patchJson("/documents/{$document->id}", [
            'name' => 'Hijacked',
        ])->assertForbidden();
    }

    // -- Archive --

    public function testItCanArchiveAnExpiredDocument()
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user, 'owner')->create([
            'expires_at' => now()->subDays(5),
        ]);

        $this->actingAs($user);

        $this->postJson("/documents/{$document->id}/archive")
            ->assertSuccessful();

        $document->refresh();
        $this->assertNotNull($document->archived_at);
    }

    public function testItCannotArchiveADocumentThatHasNotExpired()
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user, 'owner')->create([
            'expires_at' => now()->addDays(30),
        ]);

        $this->actingAs($user);

        $this->postJson("/documents/{$document->id}/archive")
            ->assertForbidden();

        $document->refresh();
        $this->assertNull($document->archived_at);
    }

    public function testItForbidsArchivingAnotherUsersDocument()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $document = Document::factory()->for($owner, 'owner')->create([
            'expires_at' => now()->subDays(5),
        ]);

        $this->actingAs($otherUser);

        $this->postJson("/documents/{$document->id}/archive")
            ->assertForbidden();
    }

    // -- Authentication --

    public function testUnauthenticatedUsersCannotAccessDocuments()
    {
        $this->getJson('/documents')->assertUnauthorized();
        $this->postJson('/documents')->assertUnauthorized();
        $this->getJson('/documents/1')->assertUnauthorized();
        $this->patchJson('/documents/1')->assertUnauthorized();
        $this->postJson('/documents/1/archive')->assertUnauthorized();
    }
}
