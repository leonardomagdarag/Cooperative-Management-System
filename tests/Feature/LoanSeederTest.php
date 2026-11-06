<?php

use App\Models\Loan;
use App\Models\User;
use Database\Seeders\LoanSeeder;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use App\Support\PermissionCatalog;

test('loan seeder always gives leo magdarag an active loan', function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(LoanSeeder::class);

    $leo = User::query()->where('email', 'leo.baluyot.magdarag039@gmail.com')->first();

    expect($leo)->not->toBeNull();

    $leoLoans = Loan::query()->where('user_id', $leo->id)->get();

    expect($leoLoans)->toHaveCount(1);
    expect($leoLoans->first()->loan_status)->toBe('active');
});

test('loan officer role is no longer part of the role catalog', function () {
    expect(PermissionCatalog::rolePresets())->not->toHaveKey('loan')
        ->and(PermissionCatalog::officerRoles())->not->toContain('loan');
});
