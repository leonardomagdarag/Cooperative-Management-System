<?php

use App\Models\Account;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CollectionRunService;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(SystemSettingSeeder::class);

    $this->branch = Branch::create([
        'code' => 'ROUTE',
        'name' => 'Route List Test Branch',
        'is_active' => true,
    ]);
    $this->officer = User::factory()->create([
        'branch_id' => $this->branch->id,
        'account_status' => 'active',
        'is_officer' => true,
        'role' => 'loan',
    ]);
    $this->officer->syncRoles(['loan']);

    $this->member = User::factory()->create(['branch_id' => $this->branch->id]);
    $this->loan = Loan::factory()->create([
        'user_id' => $this->member->id,
        'branch_id' => $this->branch->id,
        'loan_amount' => 12000,
        'interest_rate' => 0,
        'term_months' => 12,
        'loan_status' => 'active',
        'applied_at' => now()->subMonths(4),
        'approved_at' => now()->subMonths(3),
        'disbursed_at' => now()->subMonths(4),
        'penalty_amount' => 0,
    ]);
});

function collectionRouteLoanIds(object $response): array
{
    return collect($response->json('data'))->pluck('id')->all();
}

function collectionRunItemPayload(User $member, Loan $loan): array
{
    return [
        'member_id' => $member->id,
        'loan_id' => $loan->id,
        'amount' => 500,
    ];
}

test('collection run loan payments never retain a savings account association', function () {
    $account = Account::factory()->create([
        'user_id' => $this->member->id,
        'account_type' => 'savings',
    ]);
    $service = app(CollectionRunService::class);
    $run = $service->createRun($this->officer, today()->toDateString(), $this->branch->id);

    $item = $service->addItem($run, [
        ...collectionRunItemPayload($this->member, $this->loan),
        'account_id' => $account->id,
    ]);

    expect($item->account_id)->toBeNull();
});

test('an external payment notifies the officer with an open run before the loan is recorded', function () {
    $service = app(CollectionRunService::class);
    $service->createRun($this->officer, today()->toDateString(), $this->branch->id);

    $service->notifyOpenRunOfficersOfExternalPayment($this->loan, 125.50);

    $notification = Notification::query()
        ->where('user_id', $this->officer->id)
        ->where('title', 'Loan payment received during collection run')
        ->firstOrFail();
    expect($notification->message)
        ->toContain($this->loan->loan_number)
        ->toContain('125.50')
        ->toContain('Refresh the route list');
});

test('an external payment during an active run prevents duplicate collection and alerts the officer', function () {
    $loan = Loan::factory()->create([
        'user_id' => $this->member->id,
        'branch_id' => $this->branch->id,
        'loan_amount' => 12000,
        'interest_rate' => 0,
        'term_months' => 12,
        'loan_status' => 'active',
        'disbursed_at' => now()->subMonth()->startOfDay(),
        'penalty_amount' => 0,
    ]);
    expect($loan->paymentSchedule()[0]['due_date'])
        ->toBe(now()->startOfDay()->format('M d, Y'))
        ->and($loan->getCollectionMetrics()['amount_due'])->toBe(1000.0);
    $service = app(CollectionRunService::class);
    $run = $service->createRun($this->officer, today()->toDateString(), $this->branch->id);
    $service->addItem($run, [
        'member_id' => $this->member->id,
        'loan_id' => $loan->id,
        'amount' => 1000,
    ]);
    $service->submitRun($run->fresh(), $this->officer);

    $reviewer = User::where('email', 'leo.baluyot039+accountant@gmail.com')->firstOrFail();
    $manager = User::where('email', 'leo.baluyot039+manager@gmail.com')->firstOrFail();
    $inProgress = $service->approveStage($run->fresh(), $reviewer, 'approved');

    Transaction::create([
        'loan_id' => $loan->id,
        'account_id' => null,
        'transaction_type' => 'loan_payment',
        'transaction_mode' => 'online',
        'amount' => 1000,
        'penalty_amount' => 0,
        'status' => 'completed',
        'recorded_by' => $this->member->id,
        'transaction_date' => now(),
    ]);
    $service->notifyOpenRunOfficersOfExternalPayment($loan, 1000);

    $this->actingAs($this->officer)->getJson("/api/collections/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.items.0.current_amount_due', 0);

    $result = $service->approveStage($inProgress->fresh(), $manager, 'approved');

    expect($result->status)->toBe('partially_applied')
        ->and($result->items()->first()->outcome)->toBe('failed')
        ->and(Transaction::where('loan_id', $loan->id)->where('transaction_type', 'loan_payment')->count())->toBe(1)
        ->and(Notification::where('user_id', $this->officer->id)->where('title', 'Loan payment received during collection run')->exists())->toBeTrue()
        ->and(Notification::where('user_id', $this->officer->id)->where('title', 'Collection amount changed')->exists())->toBeTrue();
});

test('loans stay on the route list while their run is a draft and disappear once submitted', function () {
    $service = app(CollectionRunService::class);
    $run = $service->createRun($this->officer, today()->toDateString(), $this->branch->id);
    $service->addItem($run, collectionRunItemPayload($this->member, $this->loan));

    $draftList = $this->actingAs($this->officer)->getJson('/api/collections/route-list')->assertOk();
    expect(collectionRouteLoanIds($draftList))->toContain($this->loan->id);

    $service->submitRun($run->fresh(), $this->officer);

    $submittedList = $this->actingAs($this->officer)->getJson('/api/collections/route-list')->assertOk();
    expect(collectionRouteLoanIds($submittedList))->not->toContain($this->loan->id);
});

test('a second run on the same day cannot record a loan that is already in a submitted run', function () {
    $service = app(CollectionRunService::class);
    $firstRun = $service->createRun($this->officer, today()->toDateString(), $this->branch->id);
    $service->addItem($firstRun, collectionRunItemPayload($this->member, $this->loan));
    $service->submitRun($firstRun->fresh(), $this->officer);

    $secondRun = $service->createRun($this->officer, today()->toDateString(), $this->branch->id);
    expect($secondRun->id)->not->toBe($firstRun->id)
        ->and($secondRun->status)->toBe('draft');

    try {
        $service->addItem($secondRun, collectionRunItemPayload($this->member, $this->loan));
        $this->fail('Expected the duplicate loan to be rejected.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(409)
            ->and($exception->getMessage())->toContain($this->loan->loan_number);
    }
});

test('a loan recorded in another officer\'s submitted run is not offered on the route list', function () {
    $otherOfficer = User::factory()->create([
        'branch_id' => $this->branch->id,
        'account_status' => 'active',
        'is_officer' => true,
        'role' => 'loan',
    ]);
    $otherOfficer->syncRoles(['loan']);

    $service = app(CollectionRunService::class);
    $otherRun = $service->createRun($otherOfficer, today()->toDateString(), $this->branch->id);
    $service->addItem($otherRun, collectionRunItemPayload($this->member, $this->loan));
    $service->submitRun($otherRun->fresh(), $otherOfficer);

    $routeList = $this->actingAs($this->officer)->getJson('/api/collections/route-list')->assertOk();
    expect(collectionRouteLoanIds($routeList))->not->toContain($this->loan->id);
});
