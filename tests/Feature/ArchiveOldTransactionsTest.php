<?php

use App\Models\Account;
use App\Models\ArchivedTransaction;
use App\Models\Transaction;
use App\Models\User;

test('archive command moves transactions older than cutoff years to archived_transactions and preserves account balance', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create([
        'user_id' => $user->id,
        'balance' => 25000.00,
    ]);

    // Create 3 old transactions (> 3 years ago)
    $oldTxn1 = Transaction::factory()->create([
        'account_id' => $account->id,
        'transaction_type' => 'deposit',
        'amount' => 10000.00,
        'transaction_date' => now()->subYears(4),
        'status' => 'completed',
    ]);
    $oldTxn2 = Transaction::factory()->create([
        'account_id' => $account->id,
        'transaction_type' => 'withdrawal',
        'amount' => 2000.00,
        'transaction_date' => now()->subYears(3)->subDays(10),
        'status' => 'completed',
    ]);
    $oldTxn3 = Transaction::factory()->create([
        'account_id' => $account->id,
        'transaction_type' => 'deposit',
        'amount' => 5000.00,
        'transaction_date' => now()->subYears(5),
        'status' => 'completed',
    ]);

    // Create 2 recent transactions (< 3 years ago)
    $recentTxn1 = Transaction::factory()->create([
        'account_id' => $account->id,
        'transaction_type' => 'deposit',
        'amount' => 3000.00,
        'transaction_date' => now()->subMonths(6),
        'status' => 'completed',
    ]);
    $recentTxn2 = Transaction::factory()->create([
        'account_id' => $account->id,
        'transaction_type' => 'withdrawal',
        'amount' => 1000.00,
        'transaction_date' => now()->subDays(2),
        'status' => 'completed',
    ]);

    expect(Transaction::count())->toBe(5)
        ->and(ArchivedTransaction::count())->toBe(0);

    $initialBalance = (float) $account->fresh()->balance;
    expect($initialBalance)->toBe(25000.00);

    // Run the archive command with --years=3 and confirm 'yes'
    $this->artisan('transactions:archive', ['--years' => 3])
        ->expectsConfirmation('Do you want to archive these 3 transactions?', 'yes')
        ->assertSuccessful();

    // Verify 3 transactions were archived and removed from active transactions
    expect(Transaction::count())->toBe(2)
        ->and(ArchivedTransaction::count())->toBe(3);

    // Verify the remaining transactions are the recent ones
    $remainingIds = Transaction::pluck('id')->all();
    expect($remainingIds)->toContain($recentTxn1->id)
        ->and($remainingIds)->toContain($recentTxn2->id)
        ->and($remainingIds)->not->toContain($oldTxn1->id);

    // Verify archived transaction record properties match the original
    $archived1 = ArchivedTransaction::where('original_id', $oldTxn1->id)->first();
    expect($archived1)->not->toBeNull()
        ->and($archived1->transaction_number)->toBe($oldTxn1->transaction_number)
        ->and((float) $archived1->amount)->toBe(10000.00)
        ->and($archived1->transaction_type)->toBe('deposit')
        ->and($archived1->account_id)->toBe($account->id);

    // CRITICAL: Verify account balance remains completely intact and unaffected
    expect((float) $account->fresh()->balance)->toBe($initialBalance);
});

test('dry run mode reports counts without modifying transactions or creating archive rows', function () {
    $account = Account::factory()->create(['balance' => 15000.00]);

    Transaction::factory()->count(2)->create([
        'account_id' => $account->id,
        'transaction_type' => 'deposit',
        'transaction_date' => now()->subYears(4),
        'status' => 'completed',
    ]);

    $this->artisan('transactions:archive', ['--years' => 3, '--dry-run' => true])
        ->expectsOutputToContain('Dry run mode - no changes will be made.')
        ->assertSuccessful();

    expect(Transaction::count())->toBe(2)
        ->and(ArchivedTransaction::count())->toBe(0)
        ->and((float) $account->fresh()->balance)->toBe(15000.00);
});

test('declining archive confirmation aborts without modifying database', function () {
    $account = Account::factory()->create(['balance' => 15000.00]);

    Transaction::factory()->create([
        'account_id' => $account->id,
        'transaction_type' => 'deposit',
        'transaction_date' => now()->subYears(4),
        'status' => 'completed',
    ]);

    $this->artisan('transactions:archive', ['--years' => 3])
        ->expectsConfirmation('Do you want to archive these 1 transactions?', 'no')
        ->expectsOutputToContain('Operation cancelled.')
        ->assertSuccessful();

    expect(Transaction::count())->toBe(1)
        ->and(ArchivedTransaction::count())->toBe(0)
        ->and((float) $account->fresh()->balance)->toBe(15000.00);
});
