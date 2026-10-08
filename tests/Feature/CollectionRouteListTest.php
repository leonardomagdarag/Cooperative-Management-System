<?php

use App\Models\Branch;
use App\Models\Loan;
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
        'loan_status' => 'active',
        'applied_at' => now()->subMonths(4),
        'approved_at' => now()->subMonths(3),
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
