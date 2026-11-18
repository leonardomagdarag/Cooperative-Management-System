<?php

use App\Models\Account;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\OfficerPermissionOverride;
use App\Models\SystemSetting;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\LoanProductSeeder;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(LoanProductSeeder::class);

    $this->admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();
    $this->manager = User::where('email', 'leo.baluyot039+manager@gmail.com')->firstOrFail();
    $this->member = User::where('email', 'arjay.ramos@gmail.com')->firstOrFail();
});

test('balance sheet and financial summary compute share capital, cash inflows, and penalty-adjusted interest accurately', function () {
    $this->admin->givePermissionTo(['reports.view', 'reports.financial']);

    $branch = Branch::create(['name' => 'Audit Report Branch', 'code' => 'BR-FIN-01']);
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->assignRole('member');

    $savings = Account::create(['user_id' => $user->id, 'account_type' => 'savings', 'balance' => 2490.00]);
    $shareCapital = Account::create(['user_id' => $user->id, 'account_type' => 'share-capital', 'balance' => 500.00]);

    $date = '2026-06-15';
    // +1000 cash deposit to savings
    Transaction::create([
        'account_id' => $savings->id,
        'branch_id' => $branch->id,
        'transaction_type' => 'deposit',
        'transaction_mode' => 'counter',
        'amount' => 1000.00,
        'fee_amount' => 0.00,
        'status' => 'completed',
        'transaction_date' => $date,
        'recorded_by' => $this->manager->id,
    ]);
    // +500 cash deposit to share-capital
    Transaction::create([
        'account_id' => $shareCapital->id,
        'branch_id' => $branch->id,
        'transaction_type' => 'deposit',
        'transaction_mode' => 'counter',
        'amount' => 500.00,
        'fee_amount' => 0.00,
        'status' => 'completed',
        'transaction_date' => $date,
        'recorded_by' => $this->manager->id,
    ]);
    // -500 withdrawal with 20 fee withheld from savings (physical cash out = 500, NOT 520, fee not cash inflow)
    Transaction::create([
        'account_id' => $savings->id,
        'branch_id' => $branch->id,
        'transaction_type' => 'withdrawal',
        'transaction_mode' => 'counter',
        'amount' => 500.00,
        'fee_amount' => 20.00,
        'status' => 'completed',
        'transaction_date' => $date,
        'recorded_by' => $this->manager->id,
    ]);
    // +40 member interest credit (liability increase, NOT cash inflow)
    Transaction::create([
        'account_id' => $savings->id,
        'branch_id' => $branch->id,
        'transaction_type' => 'interest',
        'transaction_mode' => 'counter',
        'amount' => 40.00,
        'fee_amount' => 0.00,
        'status' => 'completed',
        'transaction_date' => $date,
        'recorded_by' => $this->manager->id,
    ]);

    // Loan disbursed to savings (+1970 savings, 30 fee retained, 0 physical cash out)
    $loan = Loan::factory()->create([
        'user_id' => $user->id,
        'branch_id' => $branch->id,
        'loan_amount' => 2000.00,
        'interest_rate' => 5.0,
        'term_months' => 10, // totalInterest = 1000, totalPayable = 3000 -> interest ratio = 1/3
        'loan_status' => 'active',
        'applied_at' => $date,
        'approved_at' => $date,
        'disbursed_at' => $date,
    ]);
    Transaction::create([
        'account_id' => $savings->id,
        'loan_id' => $loan->id,
        'branch_id' => $branch->id,
        'transaction_type' => 'loan_disbursement',
        'transaction_mode' => 'counter',
        'amount' => 2000.00,
        'fee_amount' => 30.00,
        'status' => 'completed',
        'transaction_date' => $date,
        'recorded_by' => $this->manager->id,
    ]);
    // Loan repayment: 350 total with 50 penalty -> 300 base repayment -> interest earned = 300 * 1/3 = 100
    Transaction::create([
        'account_id' => null,
        'loan_id' => $loan->id,
        'branch_id' => $branch->id,
        'transaction_type' => 'loan_payment',
        'transaction_mode' => 'counter',
        'amount' => 350.00,
        'penalty_amount' => 50.00,
        'fee_amount' => 0.00,
        'status' => 'completed',
        'transaction_date' => $date,
        'recorded_by' => $this->manager->id,
    ]);

    $bs = $this->actingAs($this->admin)
        ->getJson("/api/reports/balance-sheet?from=2026-06-01&to=2026-06-30&branch_id={$branch->id}")
        ->assertOk()
        ->json();

    // Cash: 1000 + 500 + 350 - 500 = 1350.00 (no phantom 20/30 fees, no 40 interest credit, no 2000 savings disbursement)
    expect((float) $bs['assets'][0]['amount'])->toBe(1350.0)
        ->and((float) $bs['liabilities'][0]['amount'])->toBe(2490.0)
        ->and((float) $bs['equity'][0]['amount'])->toBe(500.0);

    $fin = $this->actingAs($this->admin)
        ->getJson("/api/reports/financial?from=2026-06-01&to=2026-06-30&branch_id={$branch->id}")
        ->assertOk()
        ->json();

    expect((float) $fin['total_interest_earned'])->toBe(100.0)
        ->and((float) $fin['net'])->toBe(1350.0);
});

test('paying off base installments keeps accrued unpaid post-term interest extension charges due', function () {
    $this->travelTo(Carbon::parse('2026-10-23 12:00:00'));

    $loan = Loan::factory()->create([
        'user_id' => $this->member->id,
        'loan_amount' => 12000,
        'interest_rate' => 3,
        'term_months' => 1,
        'payment_frequency' => 'weekly',
        'loan_status' => 'active',
        'disbursed_at' => Carbon::parse('2026-09-01 12:00:00'),
        'interest_extension_penalty' => true,
        'penalty_amount' => 0,
    ]);

    $baseSettlement = Transaction::create([
        'loan_id' => $loan->id,
        'transaction_type' => 'loan_payment',
        'transaction_mode' => 'counter',
        'amount' => 12360.00,
        'penalty_amount' => 0.00,
        'status' => 'completed',
        'transaction_date' => '2026-10-23',
        'recorded_by' => $this->manager->id,
    ]);
    DB::table('loan_payment_installment_allocations')->insert([
        'transaction_id' => $baseSettlement->id,
        'period' => 1,
        'amount' => 12360.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Base paid on Oct 23 -> periods 2 (Oct 08), 3 (Oct 15), 4 (Oct 22) remain due (3 * 360 = 1080)
    $schedule = $loan->fresh()->paymentSchedule();
    expect($schedule)->toHaveCount(4)
        ->and((float) $loan->fresh()->remainingBalance())->toBe(1080.0);

    // Paying extension periods on Oct 30 settles them without adding Oct 29
    $this->travelTo(Carbon::parse('2026-10-30 12:00:00'));
    $extPayment = Transaction::create([
        'loan_id' => $loan->id,
        'transaction_type' => 'loan_payment',
        'transaction_mode' => 'counter',
        'amount' => 1080.00,
        'penalty_amount' => 0.00,
        'status' => 'completed',
        'transaction_date' => '2026-10-30',
        'recorded_by' => $this->manager->id,
    ]);
    foreach ([2, 3, 4] as $period) {
        DB::table('loan_payment_installment_allocations')->insert([
            'transaction_id' => $extPayment->id,
            'period' => $period,
            'amount' => 360.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    expect($loan->fresh()->paymentSchedule())->toHaveCount(4)
        ->and((float) $loan->fresh()->remainingBalance())->toBe(0.0);
});

test('spatie permission config uses default cache store and branch isolation blocks cross-branch mutations', function () {
    expect(config('permission.cache.store'))->toBe('default');

    OfficerPermissionOverride::create([
        'user_id' => $this->manager->id,
        'permission' => 'branches.bypass',
        'effect' => 'disallow',
    ]);

    $otherBranch = Branch::create(['name' => 'Other Branch', 'code' => 'BR-OTH']);
    $otherExpense = Expense::create([
        'category' => 'Utilities',
        'description' => 'Other branch bill',
        'amount' => 100.00,
        'expense_date' => now()->toDateString(),
        'recorded_by' => $this->admin->id,
        'branch_id' => $otherBranch->id,
    ]);
    $otherMember = User::factory()->create(['branch_id' => $otherBranch->id]);
    $otherMember->assignRole('member');
    $otherAccount = Account::create(['user_id' => $otherMember->id, 'account_type' => 'savings', 'balance' => 500.00]);

    $this->actingAs($this->manager);
    $this->putJson("/api/expenses/{$otherExpense->id}", [
        'category' => 'Utilities',
        'amount' => 150.00,
        'expense_date' => now()->toDateString(),
    ])->assertForbidden();
    $this->deleteJson("/api/expenses/{$otherExpense->id}")->assertForbidden();
    $this->postJson('/api/ledger', [
        'transaction_type' => 'deposit',
        'amount' => 100,
        'account_id' => $otherAccount->id,
    ])->assertForbidden();
    $this->deleteJson("/api/members/{$otherMember->id}")->assertForbidden();
});

test('services enforce active member status, product caps, co-maker filtering, bank note storage, and high-value withdrawal pending routing', function () {
    $inactive = User::factory()->create([
        'branch_id' => $this->manager->branch_id,
        'account_status' => 'inactive',
    ]);
    $inactive->assignRole('member');
    Account::create(['user_id' => $inactive->id, 'account_type' => 'savings', 'balance' => 5000.00]);

    $this->actingAs($this->manager);
    $this->postJson('/api/services/deposit', [
        'member_id' => $inactive->id,
        'amount' => 500,
        'account_type' => 'savings',
        'transaction_mode' => 'counter',
    ])->assertStatus(422);

    $this->member->update(['branch_id' => $this->manager->branch_id]);
    $savings = Account::firstOrCreate(
        ['user_id' => $this->member->id, 'account_type' => 'savings'],
        ['balance' => 20000.00]
    );
    $savings->update(['balance' => 20000.00]);

    // Immediate OTC deposit records approved_by/approved_at
    $dep = $this->postJson('/api/services/deposit', [
        'member_id' => $this->member->id,
        'amount' => 500,
        'account_type' => 'savings',
        'transaction_mode' => 'counter',
    ])->assertCreated();
    expect($dep->json('data.approved_by'))->toBe($this->manager->id);

    // Bank transfer withdrawal stores bank details on note
    $wdBank = $this->postJson('/api/services/withdrawal', [
        'member_id' => $this->member->id,
        'amount' => 500,
        'transaction_mode' => 'bank_transfer',
        'bank_name' => 'BPI',
        'account_number' => '1234-5678',
        'account_holder' => 'Arjay Ramos',
    ])->assertCreated();
    expect($wdBank->json('data.note'))->toContain('BPI')->toContain('1234-5678');

    // Officer OTC withdrawal >= threshold routes to pending so OTP approval is enforced
    SystemSetting::set('security_withdrawal_otp_threshold', 2000);
    $wdHigh = $this->postJson('/api/services/withdrawal', [
        'member_id' => $this->member->id,
        'amount' => 2500,
        'transaction_mode' => 'counter',
    ])->assertCreated();
    expect($wdHigh->json('data.status'))->toBe('pending');

    // Loan product caps and co-maker self-exclusion
    $product = LoanProduct::where('code', 'group')->firstOrFail();
    $this->actingAs($this->member);
    $this->postJson('/api/services/loan-application', [
        'loan_product_code' => $product->code,
        'amount' => (float) $product->max_amount + 1000,
        'term_months' => 3,
        'payment_frequency' => 'weekly',
        'purpose' => 'Test over limit',
        'co_makers' => [],
    ])->assertStatus(422);

    $otherMembers = User::factory()->count(3)->create(['account_status' => 'active'])->pluck('id')->all();
    $this->postJson('/api/services/loan-application', [
        'loan_product_code' => $product->code,
        'amount' => 1000,
        'term_months' => 3,
        'payment_frequency' => 'weekly',
        'purpose' => 'Test self co-maker padding',
        'co_makers' => array_merge([$this->member->id], $otherMembers),
    ])->assertStatus(422);
});

test('auth, dashboard, ticket assignment, and ledger csv export security hardening works', function () {
    // Failed API login records audit entry
    $this->postJson('/api/login', [
        'email' => $this->member->email,
        'password' => 'wrong-password',
    ])->assertStatus(422);
    expect(Activity::where('event', 'security.failed_login')->where('subject_id', $this->member->id)->exists())->toBeTrue();

    // Branchless member gets 200 on /api/dashboard
    $branchlessMember = User::factory()->create(['branch_id' => null]);
    $branchlessMember->assignRole('member');
    $this->actingAs($branchlessMember)->getJson('/api/dashboard')->assertOk();

    // Ticket cannot be assigned to a member
    $ticket = Ticket::create([
        'user_id' => $this->member->id,
        'subject' => 'Help needed',
        'category' => 'general',
        'priority' => 'low',
        'status' => 'open',
    ]);
    $this->actingAs($this->admin)
        ->patchJson("/api/tickets/{$ticket->id}/assign", ['assigned_to' => $this->member->id])
        ->assertStatus(422);

    // Ledger export handles reversals with negative sign and escapes CSV formulas
    $orig = Transaction::create([
        'transaction_type' => 'deposit',
        'amount' => 500,
        'fee_amount' => 20,
        'status' => 'completed',
        'note' => '=HYPERLINK("http://evil.example","click")',
        'branch_id' => $this->manager->branch_id,
        'transaction_date' => now(),
        'recorded_by' => $this->manager->id,
    ]);
    Transaction::create([
        'transaction_type' => 'deposit',
        'amount' => 500,
        'fee_amount' => 20,
        'status' => 'completed',
        'reversal_of_id' => $orig->id,
        'branch_id' => $this->manager->branch_id,
        'transaction_date' => now(),
        'recorded_by' => $this->manager->id,
    ]);

    $csv = $this->actingAs($this->manager)->get('/api/ledger/export?range=all')->assertOk()->streamedContent();
    expect($csv)->toContain("'=HYPERLINK")->toContain('-500.00');
});
