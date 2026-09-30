<?php

use App\Models\Account;
use App\Models\Beneficiary;
use App\Models\BoardMember;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Support\MemberRoster;

function ledgerTotals(): array
{
    $rows = Transaction::query()
        ->select('account_id', 'transaction_type', 'status', DB::raw('SUM(amount) AS total'))
        ->where('status', 'completed')
        ->groupBy('account_id', 'transaction_type', 'status')
        ->get();

    $byKey = [];
    foreach ($rows as $r) {
        $byKey[$r->account_id][$r->transaction_type] = (float) $r->total;
    }

    return $byKey;
}

test('the whole seeder runs on an empty database and produces a coherent cooperative', function () {
    $this->seed(DatabaseSeeder::class);

    // Nothing should be missing.
    expect(User::count())->toBe(16)
        ->and(Branch::count())->toBe(2)
        ->and(LoanProduct::count())->toBe(3)
        ->and(Account::count())->toBe(20)
        ->and(Loan::count())->toBe(10)
        ->and(Beneficiary::count())->toBeGreaterThan(0)
        ->and(BoardMember::count())->toBeGreaterThan(0)
        ->and(Expense::count())->toBeGreaterThan(0)
        ->and(Notification::count())->toBeGreaterThan(0)
        ->and(Transaction::count())->toBeGreaterThan(0);

    // Every member has both accounts and a complete profile.
    foreach (MemberRoster::all() as $member) {
        $user = User::where('user_number', $member['user_number'])->firstOrFail();

        expect($user->accounts()->count())->toBe(2)
            ->and($user->beneficiaries()->count())->toBeGreaterThan(0)
            ->and($user->personalData?->address)->not->toBeNull()
            ->and($user->personalData?->contact?->phone_no)->not->toBeNull()
            ->and($user->personalData?->contact?->email)->not->toBeNull();

        // Addresses are inside Calauan.
        expect($user->personalData->address->city_municipality)->toBe('Calauan')
            ->and($user->personalData->address->province)->toBe('Laguna');
    }

    // Every loan obeys its product.
    $products = LoanProduct::all()->keyBy('code');
    foreach (Loan::all() as $loan) {
        $product = $products[$loan->loan_product];
        expect($product)->not->toBeNull()
            ->and((float) $loan->loan_amount)->toBeLessThanOrEqual((float) $product->max_amount)
            ->and($loan->term_months)->toBeLessThanOrEqual($product->max_term)
            ->and((float) $loan->interest_rate)->toBe((float) $product->interest_rate)
            ->and($loan->payment_frequency)->toBe('monthly');

        // Only a released loan may carry a disbursement or payments.
        if (in_array($loan->loan_status, ['active', 'paid_off'], true)) {
            expect($loan->approved_at)->not->toBeNull();
        } else {
            expect(Transaction::where('loan_id', $loan->id)->where('transaction_type', 'loan_disbursement')->count())->toBe(0)
                ->and(Transaction::where('loan_id', $loan->id)->where('transaction_type', 'loan_payment')->count())->toBe(0);
        }
    }

    // Leo keeps exactly one active loan, as the feature test requires.
    $leo = User::where('email', 'leo.baluyot.magdarag039@gmail.com')->firstOrFail();
    expect($leo->loans()->where('loan_status', 'active')->count())->toBe(1);

    // Account balances equal the completed movements on the account.
    $totals = ledgerTotals();
    foreach (Account::all() as $account) {
        $t = $totals[$account->id] ?? [];
        $expected = ($t['deposit'] ?? 0)
            + ($t['loan_disbursement'] ?? 0)
            - ($t['withdrawal'] ?? 0)
            - ($t['loan_payment'] ?? 0);

        expect(round((float) $account->balance, 2))->toBe(round($expected, 2));
    }

    // Savings balances land on the roster's targets.
    foreach (MemberRoster::all() as $member) {
        $user = User::where('user_number', $member['user_number'])->firstOrFail();
        $savings = $user->accounts()->where('account_type', 'savings')->firstOrFail();
        expect(abs((float) $savings->balance - $member['savings']['target_balance']))->toBeLessThan(1.0);
    }

    // No completed entry is dated in the future, and no number is reused.
    expect(Transaction::where('status', 'completed')->whereDate('transaction_date', '>', now())->count())->toBe(0)
        ->and(Transaction::whereNull('transaction_number')->count())->toBe(0)
        ->and(Transaction::count())->toBe(Transaction::whereNotNull('transaction_number')->distinct('transaction_number')->count('transaction_number'));

    // Expenses fall inside the year behind us and name a real branch.
    expect(Expense::whereDate('expense_date', '>', now())->count())->toBe(0)
        ->and(Expense::whereNotIn('branch_id', Branch::pluck('id'))->count())->toBe(0);
})->skip(false);

test('seeding twice changes nothing', function () {
    $this->seed(DatabaseSeeder::class);

    $counts = fn () => [
        'users' => User::count(),
        'accounts' => Account::count(),
        'loans' => Loan::count(),
        'transactions' => Transaction::count(),
        'expenses' => Expense::count(),
        'beneficiaries' => Beneficiary::count(),
        'notifications' => Notification::count(),
        'board' => BoardMember::count(),
        'products' => LoanProduct::count(),
        'branches' => Branch::count(),
        'activity' => DB::table('activity_log')->count(),
        'settings' => DB::table('system_settings')->count(),
    ];

    $before = $counts();
    $balances = Account::pluck('balance', 'id')->all();

    $this->seed(DatabaseSeeder::class);

    expect($counts())->toBe($before)
        ->and(Account::pluck('balance', 'id')->all())->toBe($balances);
})->skip(false);

test('expenses resolve seeded branch ids instead of assuming ids start at one', function () {
    Branch::query()->create([
        'code' => 'BR999999',
        'name' => 'Pre-existing branch',
        'is_head_office' => false,
        'address' => 'Test address',
        'is_active' => true,
    ]);

    $this->seed(DatabaseSeeder::class);

    $seededBranchIds = Branch::query()
        ->whereIn('code', ['BR000001', 'BR000002'])
        ->pluck('id');

    expect($seededBranchIds)->toHaveCount(2)
        ->and(Expense::query()->whereNotIn('branch_id', $seededBranchIds)->count())->toBe(0)
        ->and(Expense::query()->where('branch_id', 1)->count())->toBe(0);
});

test('transaction factory inherits the account member branch', function () {
    $branch = Branch::query()->create([
        'code' => 'BR000001',
        'name' => 'Main',
        'is_head_office' => true,
        'address' => 'Test address',
        'is_active' => true,
    ]);
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $account = Account::factory()->for($user)->create();

    $transaction = Transaction::factory()->for($account)->create();

    expect($transaction->branch_id)->toBe($branch->id);
});
