<?php

use App\Livewire\Content\Services;
use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;

test('remaining loan balance includes interest-bearing payoff amount', function () {
    $loan = Loan::factory()->create([
        'loan_amount' => 1200,
        'interest_rate' => 12,
        'term_months' => 12,
        'loan_status' => 'active',
    ]);

    Transaction::factory()->create([
        'loan_id' => $loan->id,
        'account_id' => null,
        'transaction_type' => 'loan_payment',
        'amount' => 200,
        'status' => 'completed',
    ]);

    expect($loan->totalPayable())->toBeGreaterThan(1200.0)
        ->and($loan->remainingBalance())->toBe(round($loan->totalPayable() - 200, 2))
        ->and($loan->remainingBalance())->toBeGreaterThan(1000.0);
});

test('loan total payable applies interest per month across the term', function () {
    $loan = Loan::factory()->make([
        'loan_amount' => 3000,
        'interest_rate' => 3,
        'term_months' => 3,
    ]);

    expect($loan->totalInterest())->toBe(270.0)
        ->and($loan->totalPayable())->toBe(3270.0)
        ->and($loan->monthlyPayment())->toBe(1090.0);
});

test('online loan payment marks loan paid off only after covering the full payable amount', function () {
    Role::findOrCreate('member', 'web');

    $user = User::factory()->create(['role' => 'member']);
    $user->assignRole('member');

    $account = Account::factory()->create([
        'user_id' => $user->id,
        'account_type' => 'savings',
        'balance' => 5000,
    ]);

    $loan = Loan::factory()->create([
        'user_id' => $user->id,
        'loan_amount' => 1000,
        'interest_rate' => 12,
        'term_months' => 1,
        'payment_frequency' => 'monthly',
        'loan_status' => 'active',
    ]);

    actingAs($user);

    Livewire::test(Services::class)
        ->set('paymentLoanId', (string) $loan->id)
        ->set('paymentScheduleKey', '1')
        ->set('paymentTransactionMode', 'online')
        ->call('submitLoanPayment')
        ->assertHasNoErrors();

    expect($loan->fresh()->loan_status)->toBe('paid_off');

    $payment = Transaction::query()
        ->where('loan_id', $loan->id)
        ->where('transaction_type', 'loan_payment')
        ->latest('id')
        ->first();

    expect($payment)->not->toBeNull()
        ->and($payment->status)->toBe('completed')
        ->and((float) $loan->fresh()->remainingBalance())->toBe(0.0)
        ->and((float) $account->fresh()->balance)->toBe(5000.0);
});

test('loan payment uses the selected unpaid schedule amount', function () {
    Role::findOrCreate('member', 'web');

    $user = User::factory()->create(['role' => 'member']);
    $user->assignRole('member');

    Account::factory()->create([
        'user_id' => $user->id,
        'account_type' => 'savings',
        'balance' => 5000,
    ]);

    $loan = Loan::factory()->create([
        'user_id' => $user->id,
        'loan_amount' => 1000,
        'interest_rate' => 12,
        'term_months' => 2,
        'payment_frequency' => 'monthly',
        'loan_status' => 'active',
    ]);

    Transaction::factory()->create([
        'loan_id' => $loan->id,
        'account_id' => null,
        'transaction_type' => 'loan_payment',
        'amount' => 620,
        'status' => 'completed',
    ]);

    actingAs($user);

    Livewire::test(Services::class)
        ->set('paymentLoanId', (string) $loan->id)
        ->set('paymentScheduleKey', '2')
        ->set('paymentTransactionMode', 'counter')
        ->call('submitLoanPayment')
        ->assertHasNoErrors();

    $payment = Transaction::query()
        ->where('loan_id', $loan->id)
        ->where('transaction_type', 'loan_payment')
        ->where('status', 'pending')
        ->latest('id')
        ->first();

    expect($payment)->not->toBeNull()
        ->and((float) $payment->amount)->toBe(620.0)
        ->and((float) $loan->fresh()->remainingBalance())->toBe(620.0);
});