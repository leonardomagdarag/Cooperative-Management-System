# Finance and loan integrity session — 2026-10-22

This note records the finance, loan, and QA work completed in this work session.
The detailed finding-by-finding review remains in
[`REVIEW_UPDATE_2026-10-22.md`](../QA/finance_qa_test/runs/2026-10-21_money_integrity_pass/REVIEW_UPDATE_2026-10-22.md),
with the run-wide rollup in [`FINDINGS_REGISTER.md`](../QA/finance_qa_test/FINDINGS_REGISTER.md).

## Policy and loan lifecycle

- Updated `AGENTS.md` to state that the cooperative system is not, and must not
  become, a digital wallet. Loan repayments are external receipts and cannot
  debit member savings.
- Approved loans have no repayment schedule until disbursement. Disbursement
  records the date and destination, offers physical cash as the default and an
  explicit savings-credit alternative, highlights approved loans awaiting
  disbursement, and prevents repeat release under a loan row lock.
- Loan repayments can target selected due installments and can be partial.
  Completed installment allocations are stored by transaction and period.
- Collection routes refresh in the browser, notify officers with open runs when
  external loan payments complete, and recheck saved collection amounts under a
  loan lock before finalization. Stale amounts fail visibly instead of being
  silently rewritten.

## Finance, reports, audit, and workflow fixes

- Credited member interest is included in cash inflows and net totals while
  remaining separate from estimated loan interest collected. Report filters,
  dashboard aggregates, savings exports, transaction history, and ledger labels
  were aligned; interest has its own transaction tag and filter.
- Report and dashboard aggregates net reversal rows. History credit/debit signs
  and reversal signs were corrected.
- Money approvals use permission and branch scope without an extra role filter.
  Pending withdrawals are reserved in the officer workflow, and loan-application
  amounts retain cents.
- Manual ledger create/delete operations use the audit log. Service availability
  is enforced on approval paths. Disbursement and loan-payment paths received
  lifecycle and concurrency safeguards.
- Session and member-dashboard query state was scoped and cleared to prevent
  cross-member data reuse. A member's own counter deposit remains pending and
  does not alter the savings balance.
- The invalid cash-versus-all-account-balances finding was withdrawn. The
  savings-debit expectations for external loan repayments and online receipts
  were withdrawn under the no-wallet policy.

## Partial-payment penalty handling

- `Loan::paymentSchedule()` now subtracts previously paid penalty amounts from
  overdue schedule penalties oldest-overdue-first. This makes the schedule shown
  in the payment wizard agree with the remaining amount accepted by server-side
  validation and collection metrics.
- A frozen-time regression verifies that daily penalties remain active after a
  partial installment payment, the schedule shows only the remaining penalty
  after a penalty payment, and accrual stops once the installment is settled.
- **Product rule still needs confirmation:** `per_due` currently means a single
  flat charge per overdue installment; daily and weekly settings repeat on their
  configured cadence. The requested meaning of “flat penalty per overdue” has
  not yet established whether `per_due` stays one-time or repeats, or what the
  repeat interval should be. No penalty-frequency policy or seeded loan product
  was changed pending that clarification.

## Verification completed

- `php artisan test --filter=MoneyIntegrityRegressionTest` — 6 passed, 27
  assertions, including the new penalty regression.
- Focused loan lifecycle, business rules, service availability, collection,
  ledger, and transaction-approval suites passed in the prior verification pass;
  see the detailed review update for exact counts and scope.
- `vendor/bin/pint --test` passed for `app/Models/Loan.php` and
  `tests/Feature/MoneyIntegrityRegressionTest.php`.
- `git diff --check` passed. Git printed existing CRLF-to-LF warnings for
  unrelated modified files.
- Headless Playwright checks confirmed the green approved-undisbursed loan row,
  route refresh requests after 30 seconds, and the separate credited-interest
  report figures. No new disbursement or collection run was created in those
  browser checks.
- `Phase2WorkflowsAndPoliciesTest.php` had two unrelated failures in document
  upload and report-export permission cases; these remain noted in the detailed
  review update.

## Current status

The clarified partial-payment behavior is implemented and regression-tested.
The only open decision from this discussion is the repeat cadence for a flat
`per_due` penalty, especially for loan products currently configured with that
frequency.
