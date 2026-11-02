<?php

use App\Models\Branch;
use App\Models\CollectionRun;
use App\Models\Notification;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\CollectionRunService;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->manager = User::where('email', 'leo.baluyot039+manager@gmail.com')->firstOrFail();
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

test('a reviewer can view only runs assigned to their current approval permission', function () {
    $reviewer = User::factory()->create([
        'branch_id' => $this->branch->id,
        'account_status' => 'active',
        'is_officer' => true,
    ]);
    $reviewer->assignRole('accountant');

    $reviewRun = CollectionRun::create([
        'run_number' => 'CR-REVIEW-DETAIL',
        'loan_officer_id' => $this->manager->id,
        'collection_date' => today(),
        'branch_id' => $this->branch->id,
        'status' => 'submitted',
        'stages_snapshot' => [['key' => 'review', 'label' => 'Review', 'permission' => 'collections.review']],
        'current_stage' => 0,
    ]);
    $approveRun = CollectionRun::create([
        'run_number' => 'CR-APPROVE-DETAIL',
        'loan_officer_id' => $this->manager->id,
        'collection_date' => today(),
        'branch_id' => $this->branch->id,
        'status' => 'submitted',
        'stages_snapshot' => [['key' => 'approve', 'label' => 'Approve', 'permission' => 'collections.approve']],
        'current_stage' => 0,
    ]);

    $this->actingAs($reviewer)
        ->getJson("/api/collections/{$reviewRun->id}")
        ->assertOk()
        ->assertJsonPath('data.run_number', 'CR-REVIEW-DETAIL');

    $this->actingAs($reviewer)
        ->getJson("/api/collections/{$approveRun->id}")
        ->assertForbidden();
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

test('approval queue pages remain reachable and filters and sorts apply before pagination', function () {
    SystemSetting::updateOrCreate(['key' => 'collections.approval_stages'], [
        'label' => 'Collection approval stages',
        'value' => json_encode([['key' => 'approve', 'label' => 'Approve', 'permission' => 'collections.approve']]),
    ]);
    $runs = collect(range(1, 18))->map(fn ($number) => CollectionRun::create([
        'run_number' => sprintf('CR-PAGE-%03d', $number),
        'loan_officer_id' => $this->manager->id,
        'collection_date' => today()->subDays(18 - $number),
        'branch_id' => $this->manager->branch_id,
        'status' => 'submitted',
        'current_stage' => 0,
        'submitted_at' => now()->subMinutes(18 - $number),
        'total_amount' => $number * 10,
    ]));
    $first = $this->actingAs($this->manager)->getJson('/api/collections/queues?sort=run_number&direction=asc');
    $first->assertOk()->assertJsonPath('total', 18)->assertJsonPath('last_page', 2)->assertJsonCount(15, 'data');
    $second = $this->getJson('/api/collections/queues?page=2&sort=run_number&direction=asc');
    $second->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('data.0.run_number', 'CR-PAGE-016');
    expect(array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')))->toBe([]);
    $this->getJson('/api/collections/queues?search=CR-PAGE-018')
        ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $runs->last()->id);
    $this->getJson('/api/collections/queues?sort=total_amount&direction=desc')
        ->assertOk()->assertJsonPath('data.0.id', $runs->last()->id);
    $this->getJson('/api/collections/queues?from='.today()->toDateString().'&to='.today()->toDateString())
        ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $runs->last()->id);
});
