<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Support\LedgerWriter;
use Database\Seeders\Support\MemberRoster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Builds the loan portfolio described by MemberRoster.
 *
 * Every loan is written the way ServiceController::submitLoanApplication() and
 * LoanController would write it — the product drives the loan type and interest
 * rate, group products carry their co-makers, and interview products carry an
 * interview record. The roster is the only place the numbers come from, so a
 * loan can never be seeded above its product cap or against a member who would
 * have been refused at the counter.
 *
 * This seeder is self-sufficient: it creates the product catalogue and the
 * member accounts it depends on when they are missing, because feature tests
 * seed it on its own after only UserSeeder.
 */
class LoanSeeder extends Seeder
{
    /** The accountant handles loan review and interview records. */
    private const REVIEWER = 'USR000004';

    /** Chairman, who signs off approved loans. */
    private const APPROVER = 'USR000002';

    public function run(): void
    {
        $ledger = new LedgerWriter;
        $products = $this->products();
        $users = $this->rosteredMembers();

        if ($users->isEmpty()) {
            $this->command?->error('No rostered members found. Please run UserSeeder first.');

            return;
        }

        foreach (MemberRoster::all() as $member) {
            $user = $users[$member['user_number']] ?? null;
            $plan = $member['loan'] ?? null;

            if (! $user || ! $plan) {
                continue;
            }

            $product = $products[$plan['product']] ?? null;

            if (! $product) {
                throw new \RuntimeException("Unknown loan product [{$plan['product']}] for {$member['user_number']}.");
            }

            $appliedAt = $this->appliedAt($plan);
            $savings = Account::query()
                ->where('user_id', $user->id)
                ->where('account_type', 'savings')
                ->first();

            // Resolved first so the eligibility check can ignore the loan it is
            // about to refresh, otherwise a second seeding run trips the
            // "no existing active or pending loan" rule against itself.
            $existing = Loan::query()
                ->where('user_id', $user->id)
                ->whereDate('applied_at', $appliedAt->toDateString())
                ->first();

            $this->assertEligible($user, $product, $plan, $appliedAt, $ledger, $existing?->id);

            $loan = Loan::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'applied_at' => $appliedAt,
                ],
                array_merge(
                    $this->lifecycle($plan, $product, $user, $savings, $appliedAt, $ledger),
                    [
                        'branch_id' => $user->branch_id,
                        'account_id' => $savings?->id,
                    ]
                )
            );

            $loan->groupMembers()->sync(
                $this->coMakers($plan, $users)
            );
        }

        $this->command?->info('Loan portfolio: '.Loan::count().' loans.');
    }

    /**
     * The product catalogue, created on demand so this seeder stands alone.
     *
     * @return array<string, LoanProduct>
     */
    private function products(): array
    {
        if (LoanProduct::query()->count() === 0) {
            $this->call(LoanProductSeeder::class);
        }

        return LoanProduct::query()->where('is_active', true)->get()->keyBy('code')->all();
    }

    /**
     * The rostered members, with accounts opened on demand.
     *
     * @return Collection<string, User>
     */
    private function rosteredMembers()
    {
        $userNumbers = collect(MemberRoster::all())->pluck('user_number');

        $users = User::query()
            ->memberUsers()
            ->whereIn('user_number', $userNumbers)
            ->get()
            ->keyBy('user_number');

        if ($users->isNotEmpty() && Account::query()->whereIn('user_id', $users->pluck('id'))->count() === 0) {
            $this->call(AccountSeeder::class);
        }

        return $users;
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function appliedAt(array $plan): Carbon
    {
        if (isset($plan['applied_ago_days'])) {
            return Carbon::now()->subDays((int) $plan['applied_ago_days'])->setTime(10, 15);
        }

        return Carbon::now()
            ->subMonths((int) ($plan['applied_ago_months'] ?? 1))
            ->setTime(10, 15);
    }

    /**
     * Resolve the loan's lifecycle fields from its plan.
     *
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private function lifecycle(
        array $plan,
        LoanProduct $product,
        User $user,
        ?Account $savings,
        Carbon $appliedAt,
        LedgerWriter $ledger
    ): array {
        $reviewStatus = $plan['review_status'] ?? null;
        $isApproved = ! empty($plan['approved_ago_months']) || ! empty($plan['approved_ago_days']);
        $isDisbursed = ! empty($plan['disbursed_ago_months']);
        $disbursedAt = $isDisbursed
            ? Carbon::now()->subMonths((int) $plan['disbursed_ago_months'])->setTime(15, 0)
            : null;

        $approvedAt = null;
        if ($isApproved) {
            $approvedAt = isset($plan['approved_ago_days'])
                ? $appliedAt->copy()->addDays((int) $plan['approved_ago_days'])
                : Carbon::now()->subMonths((int) $plan['disbursed_ago_months'] ?? $plan['approved_ago_months'])->setTime(11, 0);
        }

        $termMonths = (int) $plan['term_months'];
        $loanStatus = $this->loanStatus($plan, $isApproved, $isDisbursed);

        // LoanController sets the due date when the money is actually released,
        // so an approved loan still waiting at the counter has no due date yet.
        $dueDate = $disbursedAt
            ? $disbursedAt->copy()->addMonths($termMonths)->toDateString()
            : null;

        $attributes = [
            'loan_type' => $product->base_type,
            'loan_product' => $product->code,
            'loan_amount' => $plan['amount'],
            'interest_rate' => $product->interest_rate,
            'penalty_type' => $product->penalty_type,
            'penalty_amount' => $product->penalty_amount,
            'penalty_frequency' => $product->penalty_frequency,
            'interest_extension_penalty' => $product->interest_extension_penalty,
            'term_months' => $termMonths,
            // The repayment schedule is monthly, so the seeded loans are too.
            'payment_frequency' => 'monthly',
            'loan_status' => $loanStatus,
            'review_status' => $reviewStatus,
            'reviewed_by' => $reviewStatus ? $ledger->officer(self::REVIEWER)?->id : null,
            'reviewed_at' => $reviewStatus ? $this->reviewedAt($plan, $appliedAt) : null,
            'review_notes' => $plan['review_notes'] ?? null,
            'approved_by' => $isApproved ? $ledger->officer(self::APPROVER)?->id : null,
            'approved_at' => $approvedAt,
            'disbursed_at' => $disbursedAt,
            'due_date' => $dueDate,
        ];

        return array_merge(
            $attributes,
            $this->interview($plan, $product, $user, $appliedAt, $approvedAt, $isApproved, $ledger)
        );
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function loanStatus(array $plan, bool $isApproved, bool $isDisbursed): string
    {
        if (! empty($plan['closed'])) {
            return 'paid_off';
        }

        if ($isDisbursed) {
            return 'active';
        }

        // A loan rejected during credit review is no longer a pending
        // application — mirrors LoanController::review() case 'reject'.
        if (($plan['review_status'] ?? null) === 'review_rejected') {
            return 'rejected';
        }

        // Signed off but still waiting for the money to be released.
        return $isApproved ? 'approved' : 'pending';
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function reviewedAt(array $plan, Carbon $appliedAt): ?Carbon
    {
        if (isset($plan['reviewed_ago_days'])) {
            return Carbon::now()->subDays((int) $plan['reviewed_ago_days'])->setTime(14, 0);
        }

        return null;
    }

    /**
     * Interview records, which interview-requiring products must carry.
     *
     * A business loan can only be signed off once its interview is done, so an
     * approved or active business loan always has a completed interview — either
     * the one the roster records or one dated just before the approval.
     *
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private function interview(
        array $plan,
        LoanProduct $product,
        User $user,
        Carbon $appliedAt,
        ?Carbon $approvedAt,
        bool $isApproved,
        LedgerWriter $ledger
    ): array {
        if (! $product->requires_interview) {
            return [];
        }

        $officerNumber = $plan['interview']['officer_user_number'] ?? self::REVIEWER;

        $interview = [
            'employment_id' => $user->personalData?->employment?->id,
            'interview_officer_id' => $ledger->officer($officerNumber)?->id,
            'interview_notes' => $plan['interview']['notes'] ?? null,
        ];

        $interviewCompletedOn = $plan['interview']['completed_ago_days'] ?? null;

        // An application that came back rejected was still interviewed — that
        // is what the rejection was based on.
        if ($isApproved || $interviewCompletedOn !== null) {
            $interview['interview_status'] = 'completed';
            $interview['interview_schedule'] = isset($plan['interview']['scheduled_ago_days'])
                ? Carbon::now()->subDays((int) $plan['interview']['scheduled_ago_days'])->setTime(10, 0)
                : ($approvedAt ?? $appliedAt)->copy()->subDays(3)->setTime(10, 0);
            $interview['interview_notes'] ??= 'Applicant interviewed and found creditworthy.';

            return $interview;
        }

        $interview['interview_status'] = 'scheduled';
        $interview['interview_schedule'] = $appliedAt->copy()->addDays(3)->setTime(10, 0);

        return $interview;
    }

    /**
     * Co-maker user ids for a group loan.
     *
     * @param  array<string, mixed>  $plan
     * @param  Collection<string, User>  $users
     * @return list<int>
     */
    private function coMakers(array $plan, $users): array
    {
        return collect($plan['co_maker_user_numbers'] ?? [])
            ->map(fn (string $userNumber) => $users[$userNumber] ?? null)
            ->filter()
            ->map(fn (User $user) => (int) $user->id)
            ->values()
            ->all();
    }

    /**
     * Fail loudly if the roster asks for a loan the counter would refuse.
     *
     * Mirrors ServiceController::getLoanEligibilityError(), except that share
     * capital is read as of the application date so a member is judged on the
     * subscription they actually held at the time.
     *
     * @param  array<string, mixed>  $plan
     */
    private function assertEligible(
        User $user,
        LoanProduct $product,
        array $plan,
        Carbon $appliedAt,
        LedgerWriter $ledger,
        ?int $ignoreLoanId = null
    ): void {
        $fail = function (string $reason) use ($user, $product): never {
            throw new \RuntimeException(sprintf(
                'Loan plan for %s (%s) is not eligible for product [%s]: %s',
                $user->user_number,
                $user->email,
                $product->code,
                $reason
            ));
        };

        $amount = (float) $plan['amount'];
        $term = (int) $plan['term_months'];

        if ($amount > (float) $product->max_amount) {
            $fail("amount {$amount} exceeds the product cap of {$product->max_amount}.");
        }

        if ($term > (int) $product->max_term) {
            $fail("term {$term} exceeds the product maximum of {$product->max_term} months.");
        }

        if ($product->requires_no_existing_loan && Loan::query()
            ->where('user_id', $user->id)
            ->when($ignoreLoanId, fn ($query) => $query->whereKeyNot($ignoreLoanId))
            ->whereIn('loan_status', ['pending', 'approved', 'active'])
            ->exists()) {
            $fail('the product requires no existing active or pending loan.');
        }

        if ((float) $product->minimum_share_capital > 0) {
            $held = $ledger->shareCapitalAt($user, $appliedAt);

            if ($held < (float) $product->minimum_share_capital) {
                $fail("share capital of {$held} is below the required {$product->minimum_share_capital} on {$appliedAt->toDateString()}.");
            }
        }

        if ((int) $product->minimum_membership_years > 0) {
            $years = (int) $user->created_at?->diffInYears($appliedAt);

            if ($years < (int) $product->minimum_membership_years) {
                $fail("membership of {$years} year(s) is short of the required {$product->minimum_membership_years}.");
            }
        }

        if ($product->requires_special_eligibility && ! $user->special_loan_eligible) {
            $fail('the product requires special-loan eligibility approval.');
        }

        $requiredCoMakers = (int) $product->required_co_makers;

        if ($requiredCoMakers > 0 && count($plan['co_maker_user_numbers'] ?? []) !== $requiredCoMakers) {
            $fail("the product requires exactly {$requiredCoMakers} co-makers.");
        }
    }
}
