<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Operating expenses for the twelve months to today.
 *
 * The line items describe a volunteer-run cooperative: officers serve without a
 * salary, the office space is a member-contributed property so there is no rent,
 * and the only paid staff is a part-time bookkeeper. That is what makes the
 * totals plausible next to the savings and loan books — a monthly payroll in the
 * tens of thousands would not sit alongside ₱648,200 of member savings.
 */
class ExpenseSeeder extends Seeder
{
    /** Branch codes are stable; database-generated ids are not. */
    private const MAIN = 'BR000001';

    private const BRANCH_OFFICE = 'BR000002';

    /**
     * Recurring monthly costs, per branch.
     *
     * `Main` is the larger office; the satellite branch office in Barangay
     * Halang pays for its own power and connection but shares the bookkeeper.
     *
     * @var list<array{category: string, description: string, main: float, branch: float}>
     */
    private const MONTHLY = [
        [
            'category' => 'Personnel',
            'description' => 'Part-time bookkeeper and collection aide allowance',
            'main' => 2400.00,
            'branch' => 0.00,
        ],
        [
            'category' => 'Utilities',
            'description' => 'Electricity consumption',
            'main' => 580.00,
            'branch' => 320.00,
        ],
        [
            'category' => 'Utilities',
            'description' => 'Water supply',
            'main' => 200.00,
            'branch' => 140.00,
        ],
        [
            'category' => 'Communication',
            'description' => 'Internet and telephone subscription',
            'main' => 400.00,
            'branch' => 280.00,
        ],
        [
            'category' => 'Office Supplies',
            'description' => 'Paper, ink, passbooks and printing',
            'main' => 280.00,
            'branch' => 100.00,
        ],
        [
            'category' => 'Transportation',
            'description' => 'Fuel and fares for collection errands',
            'main' => 230.00,
            'branch' => 120.00,
        ],
        [
            'category' => 'Bank Charges',
            'description' => 'Service charges on collections and remittances',
            'main' => 140.00,
            'branch' => 75.00,
        ],
        [
            'category' => 'Member Services',
            'description' => 'Drinks and snacks for the monthly members\' meeting',
            'main' => 250.00,
            'branch' => 0.00,
        ],
    ];

    /**
     * One-off costs that fall on a particular day each year.
     *
     * @var list<array{month: int, day: int, category: string, description: string, main: float, branch: float}>
     */
    private const ANNUAL = [
        [
            'month' => 4, 'day' => 14,
            'category' => 'Professional Fees',
            'description' => 'Annual audit and BIR compliance filing',
            'main' => 5000.00, 'branch' => 0.00,
        ],
        [
            'month' => 4, 'day' => 21,
            'category' => 'Member Services',
            'description' => 'Annual general assembly and election of officers',
            'main' => 3200.00, 'branch' => 0.00,
        ],
        [
            'month' => 6, 'day' => 8,
            'category' => 'Office Supplies',
            'description' => 'Printing of CAV forms, by-laws booklets and member IDs',
            'main' => 1600.00, 'branch' => 0.00,
        ],
        [
            'month' => 7, 'day' => 19,
            'category' => 'Repairs and Maintenance',
            'description' => 'Roof repair and replacement of office lighting',
            'main' => 2900.00, 'branch' => 0.00,
        ],
        [
            'month' => 9, 'day' => 3,
            'category' => 'Insurance',
            'description' => 'Fire and contents insurance for the office building',
            'main' => 1700.00, 'branch' => 0.00,
        ],
        [
            'month' => 10, 'day' => 12,
            'category' => 'Legal and Registration',
            'description' => 'Renewal of SEC registration and branch permits',
            'main' => 1300.00, 'branch' => 0.00,
        ],
        [
            'month' => 11, 'day' => 6,
            'category' => 'Repairs and Maintenance',
            'description' => 'Generator servicing and terminal deep clean',
            'main' => 1100.00, 'branch' => 0.00,
        ],
    ];

    /**
     * Keep the seeded year inside the band the financial report reads as a
     * normal operating year, so a red figure on the dashboard means something.
     */
    private const EXPECTED_MIN = 70000.00;

    private const EXPECTED_MAX = 90000.00;

    public function run(): void
    {
        $branchIds = Branch::query()
            ->whereIn('code', [self::MAIN, self::BRANCH_OFFICE])
            ->pluck('id', 'code');

        if (! $branchIds->has(self::MAIN) || ! $branchIds->has(self::BRANCH_OFFICE)) {
            throw new \RuntimeException('Seeded branches BR000001 and BR000002 before recording expenses.');
        }

        $accountant = User::query()->role('accountant')->orderBy('id')->first()
            ?? User::query()->role('admin')->orderBy('id')->first();

        if (! $accountant) {
            $this->command?->warn('No officer found to record expenses against.');

            return;
        }

        $written = 0;

        for ($offset = 11; $offset >= 0; $offset--) {
            $month = Carbon::now()->subMonths($offset)->startOfMonth();

            foreach (self::MONTHLY as $item) {
                $written += $this->record($item, $month, $accountant, 6, $branchIds->all());
            }

            foreach (self::ANNUAL as $item) {
                // Only post the one-off in the month it actually falls due.
                if ($month->month !== $item['month']) {
                    continue;
                }

                $written += $this->record($item, $month, $accountant, $item['day'], $branchIds->all());
            }
        }

        $total = (float) Expense::query()
            ->where('expense_date', '>', Carbon::now()->subYear())
            ->sum('amount');

        if ($total < self::EXPECTED_MIN || $total > self::EXPECTED_MAX) {
            throw new \RuntimeException(sprintf(
                'Operating expenses of ₱%.2f fall outside the expected ₱%.2f–₱%.2f band.',
                $total,
                self::EXPECTED_MIN,
                self::EXPECTED_MAX
            ));
        }

        $this->command?->info(sprintf(
            'Recorded %d expenses totalling ₱%s over the last twelve months.',
            $written,
            number_format($total, 2)
        ));
    }

    /**
     * Write one expense for each branch the line item applies to.
     *
     * Identity is the branch, the day and the description, so re-running the
     * seeder recognises the row instead of posting a second charge.
     *
     * @param  array{category: string, description: string, main: float, branch: float}  $item
     */
    private function record(array $item, Carbon $month, User $officer, int $day, array $branchIds): int
    {
        $date = $month->copy()->setDate($month->year, $month->month, min($day, $month->daysInMonth))->setTime(10, 30);

        if ($date->isFuture()) {
            return 0;
        }

        $written = 0;

        foreach ([$branchIds[self::MAIN] => $item['main'], $branchIds[self::BRANCH_OFFICE] => $item['branch']] as $branchId => $amount) {
            if ($amount <= 0) {
                continue;
            }

            $exists = Expense::query()
                ->where('branch_id', $branchId)
                ->where('category', $item['category'])
                ->where('description', $item['description'])
                ->whereDate('expense_date', $date->toDateString())
                ->exists();

            if ($exists) {
                continue;
            }

            $expense = new Expense;
            $expense->forceFill([
                'category' => $item['category'],
                'description' => $item['description'],
                'amount' => $amount,
                'expense_date' => $date,
                'recorded_by' => $officer->id,
                'branch_id' => $branchId,
            ]);
            $expense->save();

            $written++;
        }

        return $written;
    }
}
