<?php

use App\Models\Account;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $this->admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();

    $this->incompleteMember = User::factory()->create([
        'profile_completion_required' => true,
    ]);
    $this->incompleteMember->assignRole('member');

    $this->incompleteAccount = Account::factory()->create([
        'user_id' => $this->incompleteMember->id,
        'account_type' => 'savings',
        'balance' => 1000.00,
    ]);

    $this->completeMember = User::factory()->create([
        'profile_completion_required' => false,
    ]);
    $this->completeMember->assignRole('member');

    $this->completeAccount = Account::factory()->create([
        'user_id' => $this->completeMember->id,
        'account_type' => 'savings',
        'balance' => 1000.00,
    ]);
});

test('member with profile completion required is blocked from transaction endpoints with 403', function () {
    $this->actingAs($this->incompleteMember);

    // Deposit attempt blocked
    $response = $this->postJson('/api/services/deposit', [
        'amount' => 500,
        'account_type' => 'savings',
        'transaction_mode' => 'counter',
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('action', 'complete_profile')
        ->assertJsonPath('message', 'Profile completion required.');

    // Withdrawal attempt blocked
    $this->postJson('/api/services/withdrawal', [
        'amount' => 100,
        'transaction_mode' => 'counter',
    ])->assertStatus(403)
        ->assertJsonPath('action', 'complete_profile');

    // Loan application attempt blocked
    $this->postJson('/api/services/loan-application', [
        'loan_product_code' => 'business',
        'amount' => 5000,
        'term_months' => 6,
        'payment_frequency' => 'monthly',
        'purpose' => 'Business expansion',
    ])->assertStatus(403)
        ->assertJsonPath('action', 'complete_profile');

    // Direct loan encode endpoint blocked
    $this->postJson('/api/loans', [
        'user_id' => $this->incompleteMember->id,
        'loan_product' => 'business',
        'loan_type' => 'business',
        'loan_status' => 'pending',
        'loan_amount' => 5000,
        'interest_rate' => 3.0,
        'term_months' => 6,
        'payment_frequency' => 'monthly',
        'applied_at' => now()->toDateString(),
    ])->assertStatus(403)
        ->assertJsonPath('action', 'complete_profile');
});

test('incomplete member can access profile and profile completion read endpoints', function () {
    $this->actingAs($this->incompleteMember);

    $this->getJson('/api/profile')->assertOk();
    $this->getJson('/api/profile/completion')->assertOk();
});

test('member with completed profile is not blocked from transactions', function () {
    $this->actingAs($this->completeMember);

    $this->postJson('/api/services/deposit', [
        'amount' => 500,
        'account_type' => 'savings',
        'transaction_mode' => 'counter',
    ])->assertCreated();
});
