<?php

use App\Livewire\Content\Loans;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function createOfficerForLoanDocuments(string $role): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Role::findOrCreate($role, 'web');

    $user = User::factory()->create([
        'role' => $role,
        'is_officer' => $role !== 'member',
    ]);

    $user->assignRole($role);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

test('administrators managers and accountants can upload loan documents', function (string $role) {
    Storage::fake('public');

    $officer = createOfficerForLoanDocuments($role);
    $member = createOfficerForLoanDocuments('member');
    $loan = Loan::factory()->create([
        'user_id' => $member->id,
    ]);

    Livewire::actingAs($officer)
        ->test(Loans::class)
        ->call('viewDetails', $loan->id)
        ->set('loanDocumentUpload', UploadedFile::fake()->create('loan-checklist.pdf', 300, 'application/pdf'))
        ->call('uploadLoanDocument')
        ->assertHasNoErrors()
        ->assertDispatched('success')
        ->assertSee('loan-checklist.pdf');

    $document = $loan->fresh()->documents()->first();

    expect($document)->not->toBeNull()
        ->and($document->original_name)->toBe('loan-checklist.pdf')
        ->and($document->uploaded_by)->toBe($officer->id);

    Storage::disk('public')->assertExists($document->storage_path);
})->with(['admin', 'manager', 'accountant']);

test('other officer roles cannot upload loan documents', function () {
    Storage::fake('public');

    $loanOfficer = createOfficerForLoanDocuments('loan');
    $member = createOfficerForLoanDocuments('member');
    $loan = Loan::factory()->create([
        'user_id' => $member->id,
    ]);

    Livewire::actingAs($loanOfficer)
        ->test(Loans::class)
        ->call('viewDetails', $loan->id)
        ->set('loanDocumentUpload', UploadedFile::fake()->image('loan-proof.png'))
        ->call('uploadLoanDocument')
        ->assertForbidden();

    expect($loan->fresh()->documents()->count())->toBe(0);
});