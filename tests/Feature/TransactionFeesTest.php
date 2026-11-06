<?php

use App\Http\Controllers\Api\ReportController;
use App\Models\Account;
use App\Models\Loan;
use App\Models\PrivilegedActionRequest;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CountersignService;
use App\Support\FinancialSettings;
use Database\Seeders\AccountSeeder;
use Database\Seeders\ChairmanSeeder;
use Database\Seeders\LoanSeeder;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(ChairmanSeeder::class);
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(AccountSeeder::class);
    $this->seed(SystemSettingSeeder::class);
    $this->seed(LoanSeeder::class);
});

test('FinancialSettings fee calculation handles fixed and percentage modes with rounding', function () {
    SystemSetting::set('financial_fee_deposit_mode', 'fixed');
    SystemSetting::set('financial_fee_deposit_value', 25.50);
    SystemSetting::set('financial_fee_withdrawal_mode', 'percent');
    SystemSetting::set('financial_fee_withdrawal_value', 2.5);

    expect(FinancialSettings::feeAmount('deposit', 1000))->toBe(25.50)
        ->and(FinancialSettings::feeAmount('withdrawal', 1000))->toBe(25.00)
        ->and(FinancialSettings::feeAmount('withdrawal', 333.33))->toBe(8.33) // 333.33 * 0.025 = 8.33325 -> 8.33
        ->and(FinancialSettings::feeAmount('deposit', 0))->toBe(0.0)
        ->and(FinancialSettings::feeAmount('invalid_type', 100))->toBe(0.0);
});

test('updating financial fees via settings requires countersign and validates bounds', function () {
    $admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();
    $chairman = User::where('email', 'leo.baluyot039+chairman@gmail.com')->firstOrFail();

    // Rejection when percentage fee > 100%
    $invalidPayload = [
        'savings' => ['rate' => 3.5, 'interval' => 'monthly', 'min_balance' => 500],
        'share_capital' => ['rate' => 4.0, 'interval' => 'monthly', 'min_balance' => 0],
        'interest' => ['min_payout' => 0, 'notify_members' => false],
        'fees' => [
            'deposit' => ['mode' => 'percent', 'value' => 150],
            'withdrawal' => ['mode' => 'fixed', 'value' => 10],
            'loan_payment' => ['mode' => 'fixed', 'value' => 10],
            'loan_disbursement' => ['mode' => 'fixed', 'value' => 10],
        ],
        'withdrawal' => [
            'min_amount' => 100,
            'max_amount' => 50000,
            'savings_enabled' => true,
            'share_capital_enabled' => true,
            'enabled' => true,
            'member' => true,
            'officer' => true,
            'counter' => true,
            'online' => true,
            'bank_transfer' => true,
        ],
    ];

    $this->actingAs($admin)->putJson('/api/settings/financial', $invalidPayload)
        ->assertStatus(422);

    // Valid payload queues countersign request
    $validPayload = $invalidPayload;
    $validPayload['fees']['deposit'] = ['mode' => 'fixed', 'value' => 20];
    $validPayload['fees']['withdrawal'] = ['mode' => 'percent', 'value' => 2];

    $response = $this->actingAs($admin)->putJson('/api/settings/financial', $validPayload)
        ->assertStatus(202)
        ->assertJsonPath('status', 'pending');

    $requestId = $response->json('request_id');
    $request = PrivilegedActionRequest::findOrFail($requestId);

    expect($request->action_type)->toBe('financial.settings.update');

    // Countersign approval executes and persists settings
    $countersign = app(CountersignService::class);
    $countersign->approve($request, $chairman);

    expect(SystemSetting::get('financial_fee_deposit_mode'))->toBe('fixed')
        ->and((float) SystemSetting::get('financial_fee_deposit_value'))->toBe(20.0)
        ->and(SystemSetting::get('financial_fee_withdrawal_mode'))->toBe('percent')
        ->and((float) SystemSetting::get('financial_fee_withdrawal_value'))->toBe(2.0);
});

test('deposit captures fee_amount and officer deposit credits principal', function () {
    SystemSetting::set('financial_fee_deposit_mode', 'fixed');
    SystemSetting::set('financial_fee_deposit_value', 15.0);

    $officer = User::where('email', 'leo.baluyot039+accountant@gmail.com')->firstOrFail();
    $member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
    $account = Account::where('user_id', $member->id)->where('account_type', 'savings')->firstOrFail();
    $initialBalance = (float) $account->balance;

    $res = $this->actingAs($officer)->postJson('/api/services/deposit', [
        'member_id' => $member->id,
        'amount' => 500,
        'account_type' => 'savings',
        'transaction_mode' => 'counter',
    ])->assertCreated();

    $txn = Transaction::findOrFail($res->json('data.id'));
    expect((float) $txn->fee_amount)->toBe(15.0)
        ->and((float) $txn->amount)->toBe(500.0)
        ->and((float) $account->fresh()->balance)->toBe($initialBalance + 500.0);
});

test('withdrawal enforces balance including fee and decrements amount plus fee on approval', function () {
    SystemSetting::set('financial_fee_withdrawal_mode', 'percent');
    SystemSetting::set('financial_fee_withdrawal_value', 2.0); // 2% fee

    $member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
    $account = Account::where('user_id', $member->id)->where('account_type', 'savings')->firstOrFail();
    $account->update(['balance' => 1010.00]); // available is 1010

    // Attempting to withdraw 1000 requires 1000 + 20 fee = 1020, exceeding 1010 balance
    $this->actingAs($member)->postJson('/api/services/withdrawal', [
        'amount' => 1000,
        'reason' => 'Testing fee limit',
        'transaction_mode' => 'counter',
    ])->assertStatus(422);

    // Withdrawing 900 requires 900 + 18 fee = 918 <= 1010 (allowed)
    $res = $this->actingAs($member)->postJson('/api/services/withdrawal', [
        'amount' => 900,
        'reason' => 'Testing fee limit pass',
        'transaction_mode' => 'counter',
    ])->assertCreated();

    $txn = Transaction::findOrFail($res->json('data.id'));
    expect((float) $txn->fee_amount)->toBe(18.0)
        ->and($txn->status)->toBe('pending');

    // Officer approves withdrawal: balance decreases by 900 + 18 = 918
    $manager = User::where('email', 'leo.baluyot039+manager@gmail.com')->firstOrFail();
    $this->actingAs($manager)->postJson("/api/services/approve/{$txn->id}", [
        'action' => 'approve',
    ])->assertOk();

    expect((float) $account->fresh()->balance)->toBe(1010.00 - 918.00); // 92.00
});

test('loan disbursement fee reduces savings credit and is tracked on transaction', function () {
    SystemSetting::set('financial_fee_loan_disbursement_mode', 'percent');
    SystemSetting::set('financial_fee_loan_disbursement_value', 1.5); // 1.5% fee

    $manager = User::where('email', 'leo.baluyot039+manager@gmail.com')->firstOrFail();
    $loan = Loan::where('loan_status', 'approved')->firstOrFail();
    $borrower = $loan->user;
    $savings = Account::where('user_id', $borrower->id)->where('account_type', 'savings')->firstOrFail();
    $initialBalance = (float) $savings->balance;
    $loanAmount = (float) $loan->loan_amount;
    $expectedFee = round($loanAmount * 0.015, 2);
    $netCredited = $loanAmount - $expectedFee;

    $this->actingAs($manager)->postJson("/api/loans/{$loan->id}/disburse", [
        'destination' => 'savings',
    ])->assertOk();

    $txn = Transaction::where('loan_id', $loan->id)->where('transaction_type', 'loan_disbursement')->firstOrFail();
    expect((float) $txn->fee_amount)->toBe($expectedFee)
        ->and((float) $savings->fresh()->balance)->toBe($initialBalance + $netCredited);
});

test('reversing a transaction with fees correctly rolls back account balance and reports', function () {
    SystemSetting::set('financial_fee_withdrawal_mode', 'fixed');
    SystemSetting::set('financial_fee_withdrawal_value', 25.0);

    $manager = User::where('email', 'leo.baluyot039+manager@gmail.com')->firstOrFail();
    $member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
    $account = Account::where('user_id', $member->id)->where('account_type', 'savings')->firstOrFail();
    $initialBalance = (float) $account->balance;

    // Create completed withdrawal of 500 with 25 fee (account decremented by 525)
    $txn = Transaction::create([
        'account_id' => $account->id,
        'branch_id' => $account->branch_id,
        'transaction_type' => 'withdrawal',
        'amount' => 500,
        'fee_amount' => 25,
        'status' => 'completed',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $manager->id,
        'approved_by' => $manager->id,
    ]);
    $account->decrement('balance', 525);

    // Verify financial report includes the 25 fee
    $reportController = app(ReportController::class);
    $req = Request::create('/api/reports/financial', 'GET', [
        'start' => now()->startOfMonth()->toDateString(),
        'end' => now()->endOfMonth()->toDateString(),
    ]);
    Auth::login($manager);
    $repBefore = $reportController->financial($req)->getData(true);

    // Perform reversal of withdrawal: restores amount + fee_amount (525) to account
    $account->increment('balance', (float) $txn->amount + (float) $txn->fee_amount);
    $reversal = Transaction::create([
        'reversal_of_id' => $txn->id,
        'account_id' => $txn->account_id,
        'loan_id' => null,
        'amount' => $txn->amount,
        'fee_amount' => $txn->fee_amount,
        'transaction_date' => now(),
        'status' => 'completed',
        'approved_by' => $manager->id,
        'recorded_by' => $manager->id,
        'transaction_type' => $txn->transaction_type,
        'transaction_mode' => $txn->transaction_mode,
        'branch_id' => $txn->branch_id,
        'note' => 'Reversal test',
    ]);

    expect((float) $account->fresh()->balance)->toBe($initialBalance);

    $repAfter = $reportController->financial($req)->getData(true);
    expect((float) $repAfter['total_fees'])->toBe((float) ($repBefore['total_fees'] - 25.0));
});
