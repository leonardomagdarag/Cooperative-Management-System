<?php

use App\Models\Branch;
use App\Models\CollectionRun;
use App\Models\Notification;
use App\Models\User;
use App\Services\CollectionRunService;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->manager = User::where('email', 'manager@example.com')->firstOrFail();
    $this->branch = Branch::create([
        'code' => 'TEST',
        'name' => 'Collection Test Branch',
        'is_active' => true,
    ]);
});

test('a collection officer cannot retry failures on their own run even when they can approve', function () {
    $run = CollectionRun::create([
        'run_number' => 'CR-SELF-RETRY',
        'loan_officer_id' => $this->manager->id,
        'collection_date' => today(),
        'branch_id' => $this->branch->id,
        'status' => 'partially_applied',
    ]);

    expect(fn () => app(CollectionRunService::class)->retryFailedItems($run, $this->manager, 'Retry the recorded collection'))
        ->toThrow(HttpException::class);
});

test('collection approval queue loads using the submission timestamp', function () {
    $this->actingAs($this->manager)
        ->getJson('/api/collections/queues')
        ->assertOk()
        ->assertJsonStructure(['data', 'current_page', 'last_page', 'stalled']);
});

test('a rejected run creates one linked replacement for an active officer', function () {
    $officer = User::factory()->create([
        'branch_id' => $this->branch->id,
        'account_status' => 'active',
        'is_officer' => true,
    ]);
    $officer->assignRole('loan');

    $rejected = CollectionRun::create([
        'run_number' => 'CR-REJECTED',
        'loan_officer_id' => $officer->id,
        'collection_date' => today(),
        'branch_id' => $this->branch->id,
        'status' => 'rejected',
        'submitted_at' => now(),
    ]);

    $replacement = app(CollectionRunService::class)->createReplacementRun($rejected, $this->manager, $officer);

    expect($replacement->status)->toBe('draft')
        ->and($replacement->resubmission_of_id)->toBe($rejected->id)
        ->and($rejected->fresh()->resubmission()->count())->toBe(1);
});

test('deactivating a loan officer marks draft runs orphaned and notifies collection viewers', function () {
    $officer = User::factory()->create([
        'branch_id' => $this->branch->id,
        'account_status' => 'active',
        'is_officer' => true,
    ]);
    $officer->assignRole('loan');
    $run = CollectionRun::create([
        'run_number' => 'CR-ORPHAN',
        'loan_officer_id' => $officer->id,
        'collection_date' => today(),
        'branch_id' => $this->branch->id,
        'status' => 'draft',
    ]);

    expect(app(CollectionRunService::class)->markDraftRunsOrphaned($officer))->toBe(1);
    expect($run->fresh()->orphaned_at)->not->toBeNull()
        ->and(Notification::where('user_id', $this->manager->id)->where('related_id', $run->id)->exists())->toBeTrue();
});

test('manager can reassign an orphaned draft to an active loan officer', function () {
    $formerOfficer = User::factory()->create([
        'branch_id' => $this->branch->id,
        'account_status' => 'active',
        'is_officer' => true,
    ]);
    $formerOfficer->assignRole('loan');
    $run = CollectionRun::create([
        'run_number' => 'CR-REASSIGN',
        'loan_officer_id' => $formerOfficer->id,
        'collection_date' => today(),
        'branch_id' => $this->branch->id,
        'status' => 'draft',
        'orphaned_at' => now(),
    ]);
    $formerOfficer->delete();

    $newOfficer = User::factory()->create([
        'branch_id' => $this->branch->id,
        'account_status' => 'active',
        'is_officer' => true,
    ]);
    $newOfficer->assignRole('loan');

    $reassigned = app(CollectionRunService::class)->reassignOrphanedRun(
        $run,
        $this->manager,
        $newOfficer,
        'Cover the former officer route'
    );

    expect($reassigned->loan_officer_id)->toBe($newOfficer->id)
        ->and($reassigned->orphaned_at)->toBeNull();
});
