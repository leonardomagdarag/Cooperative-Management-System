<?php

use App\Models\Account;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\InterestDisbursement;
use App\Models\InterestDisbursementItem;
use App\Models\Loan;
use App\Models\OfficerPermissionOverride;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->officer = User::factory()->create(['role' => 'accountant', 'is_officer' => true]);
    $this->officer->assignRole('accountant');
    $this->actingAs($this->officer);
});

function openReportDownload($response): Spreadsheet
{
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($response->headers->get('Content-Disposition'))->toContain('.xlsx');
    $file = tempnam(sys_get_temp_dir(), 'report-test-');
    try {
        file_put_contents($file, $response->streamedContent());

        return IOFactory::load($file);
    } finally {
        unlink($file);
    }
}

test('excel contains all filtered records with numeric money dates and safe literal text', function () {
    foreach (range(1, 12) as $index) {
        Expense::create(['category' => 'Utilities', 'description' => '=1+1', 'amount' => 12.50, 'expense_date' => '2026-09-10', 'recorded_by' => $this->officer->id]);
    }
    Expense::create(['category' => 'Travel', 'description' => 'Excluded category', 'amount' => 99, 'expense_date' => '2026-09-10']);
    Expense::create(['category' => 'Utilities', 'description' => 'Excluded date', 'amount' => 99, 'expense_date' => '2026-08-10']);
    $filters = ['from' => '2026-09-01', 'to' => '2026-09-30', 'category' => 'Utilities'];
    $this->getJson('/api/reports/expenses?'.http_build_query($filters))->assertOk()->assertJsonPath('meta.total', 12)->assertJsonPath('total', 150);

    $book = openReportDownload($this->postJson('/api/reports/export', $filters + ['report_type' => 'expenses', 'format' => 'excel', 'page' => 2]));
    $sheet = $book->getActiveSheet();
    expect($sheet->getCell('B9')->getValue())->toBe('=1+1')
        ->and($sheet->getCell('B9')->getDataType())->toBe('s')
        ->and($sheet->getCell('C9')->getValue())->toBe(12.5)
        ->and($sheet->getCell('C9')->getDataType())->toBe('n')
        ->and(Date::excelToDateTimeObject($sheet->getCell('D9')->getValue())->format('Y-m-d'))->toBe('2026-09-10')
        ->and($sheet->getCell('B20')->getValue())->toBe('=1+1')
        ->and($sheet->getCell('C22')->getValue())->toEqual(150)
        ->and($sheet->getFreezePane())->toBe('A9')
        ->and($sheet->getAutoFilter()->getRange())->toBe('A8:E20');
    $book->disconnectWorksheets();
});

test('transaction export preserves filtered sorting and signed reversals', function () {
    $member = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $member->id, 'account_type' => 'savings']);
    $original = Transaction::factory()->create(['account_id' => $account->id, 'transaction_type' => 'deposit', 'status' => 'completed', 'transaction_date' => '2026-09-04', 'amount' => 80]);
    Transaction::factory()->create(['account_id' => $account->id, 'transaction_type' => 'deposit', 'status' => 'completed', 'transaction_date' => '2026-09-05', 'amount' => 80, 'reversal_of_id' => $original->id]);
    Transaction::factory()->create(['account_id' => $account->id, 'transaction_type' => 'deposit', 'status' => 'pending', 'transaction_date' => '2026-09-06', 'amount' => 500]);
    $filters = ['from' => '2026-09-01', 'to' => '2026-09-30', 'member_id' => $member->id, 'activity_type' => 'deposit', 'sort_by' => 'date', 'sort_direction' => 'asc'];
    $preview = $this->getJson('/api/reports/member-activity?'.http_build_query($filters))->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('total', 0)->json('data');
    $book = openReportDownload($this->postJson('/api/reports/export', $filters + ['report_type' => 'member_activity', 'format' => 'excel']));
    $sheet = $book->getActiveSheet();
    expect($sheet->getCell('A9')->getValue())->toBe($preview[0]['transaction_number'])
        ->and($sheet->getCell('A10')->getValue())->toBe($preview[1]['transaction_number'])
        ->and($sheet->getCell('D9')->getValue())->toEqual(80)
        ->and($sheet->getCell('D10')->getValue())->toEqual(-80);
    $book->disconnectWorksheets();
});

test('transaction totals cover the full filter even when viewing the second page', function () {
    $member = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $member->id, 'account_type' => 'savings']);
    Transaction::factory()->count(12)->create(['account_id' => $account->id, 'transaction_type' => 'deposit', 'status' => 'completed', 'transaction_date' => '2026-09-04', 'amount' => 10]);
    $this->getJson('/api/reports/member-activity?from=2026-09-01&to=2026-09-30&page=2')
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('total', 120);
});

test('loan register keeps pending statuses and excludes loans due today from overdue results', function () {
    $this->travelTo(now()->setDate(2026, 9, 15)->setTime(12, 0));
    Loan::factory()->pending()->create(['applied_at' => '2026-09-01', 'loan_amount' => 1000]);
    Loan::factory()->create(['loan_status' => 'active', 'applied_at' => '2026-09-02', 'due_date' => '2026-09-15', 'loan_amount' => 2000]);
    Loan::factory()->create(['loan_status' => 'active', 'applied_at' => '2026-09-03', 'due_date' => '2026-09-14', 'loan_amount' => 3000]);
    $filters = ['from' => '2026-09-01', 'to' => '2026-09-30'];
    $preview = $this->getJson('/api/reports/loans?'.http_build_query($filters))->assertOk()->json('data');
    expect(collect($preview)->pluck('status')->sort()->values()->all())->toBe(['Active', 'Overdue', 'Pending']);
    $this->getJson('/api/reports/loans?'.http_build_query($filters + ['filter' => 'overdue']))->assertOk()->assertJsonPath('meta.total', 1);
    $book = openReportDownload($this->postJson('/api/reports/export', $filters + ['report_type' => 'loans', 'format' => 'excel']));
    $sheet = $book->getActiveSheet();
    expect([$sheet->getCell('I9')->getValue(), $sheet->getCell('I10')->getValue(), $sheet->getCell('I11')->getValue()])->toBe(array_column($preview, 'status'));
    $book->disconnectWorksheets();
    $this->travelBack();
});

test('view access alone cannot download and effective disallows block every route', function () {
    OfficerPermissionOverride::create(['user_id' => $this->officer->id, 'permission' => 'reports.export', 'effect' => 'disallow']);
    $this->getJson('/api/reports/financial')->assertOk();
    $this->postJson('/api/reports/export', ['report_type' => 'financial_report', 'format' => 'excel'])->assertForbidden();
    $this->get('/api/reports/balance-sheet/download')->assertForbidden();
    OfficerPermissionOverride::create(['user_id' => $this->officer->id, 'permission' => 'interest.view', 'effect' => 'disallow']);
    $this->postJson('/api/reports/export', ['report_type' => 'interest_disbursements', 'format' => 'excel'])->assertForbidden();
    OfficerPermissionOverride::where('user_id', $this->officer->id)->first()->delete();
    OfficerPermissionOverride::create(['user_id' => $this->officer->id, 'permission' => 'reports.financial', 'effect' => 'disallow']);
    $this->postJson('/api/reports/export', ['report_type' => 'balance_sheet', 'format' => 'pdf'])->assertForbidden();
    $this->get('/api/reports/balance-sheet/download')->assertForbidden();
    OfficerPermissionOverride::create(['user_id' => $this->officer->id, 'permission' => 'reports.view', 'effect' => 'disallow']);
    $this->postJson('/api/reports/export', ['report_type' => 'expenses', 'format' => 'excel'])->assertForbidden();
});

test('interest previews drilldowns and exports scope shared batches to the officers branch', function () {
    $main = Branch::create(['code' => 'REPORT-A', 'name' => 'Report A', 'is_active' => true]);
    $other = Branch::create(['code' => 'REPORT-B', 'name' => 'Report B', 'is_active' => true]);
    $this->officer->update(['branch_id' => $main->id]);
    $this->officer->givePermissionTo('interest.view');
    OfficerPermissionOverride::create(['user_id' => $this->officer->id, 'permission' => 'branches.bypass', 'effect' => 'disallow']);
    $batch = InterestDisbursement::create(['account_type' => 'savings', 'rate' => 2, 'interval' => 'monthly', 'period_label' => '2026-09', 'batch_key' => 'report-scope', 'member_count' => 2, 'total_amount' => 300]);
    $batch->forceFill(['created_at' => '2026-09-15 10:00:00'])->save();
    foreach ([[$main, 100], [$other, 200]] as [$branch, $amount]) {
        $member = User::factory()->create(['branch_id' => $branch->id]);
        InterestDisbursementItem::create(['interest_disbursement_id' => $batch->id, 'user_id' => $member->id, 'amount' => $amount, 'balance_before' => 1000, 'balance_after' => 1000 + $amount, 'rate' => 2, 'skipped' => false]);
        Expense::create(['category' => 'Utilities', 'description' => $branch->name, 'branch_id' => $branch->id, 'amount' => $amount, 'expense_date' => '2026-09-15']);
    }
    $filters = ['from' => '2026-09-01', 'to' => '2026-09-30', 'branch_id' => $other->id, 'account_type' => 'savings'];
    $this->getJson('/api/reports/interest-disbursements?'.http_build_query($filters))->assertOk()->assertJsonPath('data.0.member_count', 1)->assertJsonPath('data.0.total_amount', 100);
    $this->getJson('/api/reports/interest-disbursements/'.$batch->id.'?branch_id='.$other->id)->assertOk()->assertJsonPath('batch.member_count', 1)->assertJsonPath('batch.total_amount', 100)->assertJsonCount(1, 'data');
    $book = openReportDownload($this->postJson('/api/reports/export', $filters + ['report_type' => 'interest_disbursements', 'format' => 'excel']));
    expect($book->getActiveSheet()->getCell('F9')->getValue())->toEqual(1)
        ->and($book->getActiveSheet()->getCell('H9')->getValue())->toEqual(100)
        ->and($book->getActiveSheet()->getCell('A4')->getValue())->toContain('Report A');
    $book->disconnectWorksheets();
    $this->getJson('/api/reports/interest-disbursements?from=2026-10-01&to=2026-10-31')->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson('/api/reports/interest-disbursements?account_type=share-capital')->assertOk()->assertJsonPath('meta.total', 0);
    $book = openReportDownload($this->postJson('/api/reports/export', ['report_type' => 'expenses', 'format' => 'excel', 'from' => '2026-09-01', 'to' => '2026-09-30', 'branch_id' => $other->id]));
    expect($book->getActiveSheet()->getCell('B9')->getValue())->toBe('Report A')
        ->and($book->getActiveSheet()->getCell('C9')->getValue())->toEqual(100);
    $book->disconnectWorksheets();
});

test('invalid dates filters and formats return validation errors', function () {
    foreach ([['from' => 'bad-date'], ['from' => '2026-09-30', 'to' => '2026-09-01'], ['activity_type' => 'invalid'], ['sort_by' => 'DROP TABLE transactions']] as $filters) {
        $this->getJson('/api/reports/member-activity?'.http_build_query($filters))->assertUnprocessable();
        $this->postJson('/api/reports/export', $filters + ['report_type' => 'member_activity', 'format' => 'excel'])->assertUnprocessable();
    }
    $this->postJson('/api/reports/export', ['report_type' => 'unknown', 'format' => 'excel'])->assertUnprocessable();
    $this->postJson('/api/reports/export', ['report_type' => 'expenses', 'format' => 'zip'])->assertUnprocessable();
    $this->postJson('/api/reports/export', ['report_type' => ['expenses'], 'format' => 'excel'])->assertUnprocessable();
});

test('every finance report generates a real workbook and readable pdf including empty reports', function (string $type) {
    $this->officer->givePermissionTo('interest.view');
    $filters = ['report_type' => $type, 'from' => '2026-09-01', 'to' => '2026-09-30'];
    $book = openReportDownload($this->postJson('/api/reports/export', $filters + ['format' => 'excel']));
    expect($book->getActiveSheet()->getCell('A1')->getValue())->not->toBeEmpty();
    if ($type === 'balance_sheet') {
        expect($book->getActiveSheet()->getCell('A5')->getValue())->toContain('not an audited');
    }
    $book->disconnectWorksheets();
    $response = $this->postJson('/api/reports/export', $filters + ['format' => 'pdf'])->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and(substr($response->streamedContent(), 0, 5))->toBe('%PDF-');
})->with(['financial_report', 'balance_sheet', 'member_savings', 'loans', 'payments', 'expenses', 'member_activity', 'interest_disbursements']);
