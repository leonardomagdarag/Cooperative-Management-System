<?php

use App\Models\Loan;
use App\Models\LoanDocument;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    Storage::fake('local');
    Storage::fake('public');
});

test('loan document upload stores file on private local disk and not public disk', function () {
    $admin = User::where('email', 'admin@example.com')->firstOrFail();
    $borrower = User::factory()->create();
    $borrower->assignRole('member');

    $loan = Loan::factory()->create([
        'user_id' => $borrower->id,
        'loan_status' => 'approved',
    ]);

    $file = UploadedFile::fake()->create('payslip.pdf', 100, 'application/pdf');

    $response = $this->actingAs($admin)
        ->postJson("/api/loans/{$loan->id}/documents", [
            'document' => $file,
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'document' => ['id', 'original_name', 'mime_type', 'file_size', 'url', 'uploaded_at'],
        ]);

    $docId = $response->json('document.id');
    $document = LoanDocument::findOrFail($docId);

    // Assert stored on private disk, NOT public disk
    Storage::disk('local')->assertExists($document->storage_path);
    Storage::disk('public')->assertMissing($document->storage_path);

    // Assert URL routes through authenticated API endpoint
    expect($response->json('document.url'))->toContain("/api/loans/{$loan->id}/documents/{$docId}");
});

test('unauthenticated users cannot download loan documents', function () {
    $borrower = User::factory()->create();
    $loan = Loan::factory()->create(['user_id' => $borrower->id]);

    $path = 'loan-documents/'.$loan->id.'/secret.pdf';
    Storage::disk('local')->put($path, 'secret file content');

    $document = $loan->documents()->create([
        'original_name' => 'secret.pdf',
        'storage_path' => $path,
        'mime_type' => 'application/pdf',
        'file_size' => 19,
        'uploaded_by' => $borrower->id,
    ]);

    $response = $this->getJson("/api/loans/{$loan->id}/documents/{$document->id}");
    $response->assertStatus(401);
});

test('borrower can download their own loan document', function () {
    $borrower = User::factory()->create();
    $borrower->assignRole('member');

    $loan = Loan::factory()->create(['user_id' => $borrower->id]);

    $path = 'loan-documents/'.$loan->id.'/statement.pdf';
    Storage::disk('local')->put($path, 'statement content');

    $document = $loan->documents()->create([
        'original_name' => 'statement.pdf',
        'storage_path' => $path,
        'mime_type' => 'application/pdf',
        'file_size' => 17,
        'uploaded_by' => $borrower->id,
    ]);

    $response = $this->actingAs($borrower)
        ->get("/api/loans/{$loan->id}/documents/{$document->id}");

    $response->assertOk();
    expect($response->streamedContent())->toBe('statement content');
});

test('another member cannot download a borrower loan document', function () {
    $borrower = User::factory()->create();
    $borrower->assignRole('member');

    $otherMember = User::factory()->create();
    $otherMember->assignRole('member');

    $loan = Loan::factory()->create(['user_id' => $borrower->id]);

    $path = 'loan-documents/'.$loan->id.'/confidential.pdf';
    Storage::disk('local')->put($path, 'confidential content');

    $document = $loan->documents()->create([
        'original_name' => 'confidential.pdf',
        'storage_path' => $path,
        'mime_type' => 'application/pdf',
        'file_size' => 20,
        'uploaded_by' => $borrower->id,
    ]);

    $response = $this->actingAs($otherMember)
        ->get("/api/loans/{$loan->id}/documents/{$document->id}");

    $response->assertForbidden();
});

test('officer with loans.view permission can download borrower loan document', function () {
    $admin = User::where('email', 'admin@example.com')->firstOrFail();
    $borrower = User::factory()->create();
    $borrower->assignRole('member');

    $loan = Loan::factory()->create(['user_id' => $borrower->id]);

    $path = 'loan-documents/'.$loan->id.'/deed.pdf';
    Storage::disk('local')->put($path, 'deed content');

    $document = $loan->documents()->create([
        'original_name' => 'deed.pdf',
        'storage_path' => $path,
        'mime_type' => 'application/pdf',
        'file_size' => 12,
        'uploaded_by' => $borrower->id,
    ]);

    $response = $this->actingAs($admin)
        ->get("/api/loans/{$loan->id}/documents/{$document->id}");

    $response->assertOk();
    expect($response->streamedContent())->toBe('deed content');
});

test('migrate documents command relocates public loan documents to private local storage', function () {
    $borrower = User::factory()->create();
    $loan = Loan::factory()->create(['user_id' => $borrower->id]);

    $path = 'loan-documents/'.$loan->id.'/legacy.pdf';
    // Seed on public disk
    Storage::disk('public')->put($path, 'legacy public content');

    $document = $loan->documents()->create([
        'original_name' => 'legacy.pdf',
        'storage_path' => $path,
        'mime_type' => 'application/pdf',
        'file_size' => 21,
        'uploaded_by' => $borrower->id,
    ]);

    Storage::disk('public')->assertExists($path);
    Storage::disk('local')->assertMissing($path);

    $this->artisan('loans:migrate-documents-storage')
        ->assertSuccessful()
        ->expectsOutputToContain('Successfully migrated 1 loan document(s)');

    Storage::disk('public')->assertMissing($path);
    Storage::disk('local')->assertExists($path);
    expect(Storage::disk('local')->get($path))->toBe('legacy public content');
});
