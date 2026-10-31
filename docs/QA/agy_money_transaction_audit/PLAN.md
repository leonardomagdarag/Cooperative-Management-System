# Money and transaction audit plan

## Objective

Verify the current application’s money-related workflows end to end through
the real SPA, using role-specific browser sessions and independent before/after
database measurements. Focus on whether records, balances, loan state, reports,
and displayed values agree; whether money moves exactly once; and whether role,
branch, approval, and lifecycle boundaries hold.

A case passes only when its reviewed expectation is supported by the browser
result, observed network boundary, and persisted state or explicit no-write
proof. A page render, toast, API response alone, or code review alone is not a
pass for a stateful case.

## Required sources and current-state review

At run start, read in order: `AGENTS.md`; money-related sections of
`FUNCTIONALIT_LIST.md`; current `docs/QA/QA_LOG.md` and
`docs/QA/CODE_DOCUMENT_ALIGNMENT.md`; `docs/DFD_SIDEBAR_FUNCTIONAL_INVENTORY.md`,
`docs/DFD_MODAL_FUNCTIONAL_INVENTORY.md`,
`docs/DFD_MASTER_SIDEBAR_PROCESS_FLOWS.md`,
`docs/Diagrams (New)/sidebar_process_map.json`, and role verification maps;
this packet; then the relevant sections of
`docs/QA/money_correctness_qa/{README,PLAN,TEST_MATRIX,SYSTEM_MAP,MONEY_INVARIANTS,CONVENTIONS,FINDINGS_REGISTER}.md`
and its latest run artifacts. Inspect relevant current code, migrations,
permissions/seeders, routes, frontend gates, and existing measurement scripts.
Historical runs and maps identify leads and coverage; the current backend,
runtime behavior, and live data decide expectations.

Rebuild role matrices from current permissions and process maps before each
role. Refresh money invariants and measurement recipes where code or schema
has changed. Treat the October 22, 2026 baseline and all older expected values
as stale. Do not edit application code as QA. Update QA-only scripts only if
needed for accurate, read-only measurement, review the change, and record it.

## Scope

Cover applicable flows across all seven audiences, in the specified sequence:

1. Guest — public service/fee claims and authentication entry points; no
   account data access or money mutation.
2. Member — personal balances/history; deposits, withdrawals, loan application,
   externally funded loan payments, receipts, and own-record isolation.
3. Loan Officer (`loan`) — loan review/lifecycle, schedule, disbursement
   delivery choice, repayment records, collections where permitted, and
   branch/member boundaries.
4. Accountant — transaction entry/review boundaries, ledger, expenses, reports,
   receipts, and separation from approval/countersign authority.
5. Manager — permitted service approvals, collections stages, loan/transaction
   workflows, expenses/reports, branch boundaries, and separation of duties.
6. Chairman — countersign/oversight for eligible money actions, financial
   settings and interest workflows where permitted, audit linkage, and
   self-approval denial.
7. Administrator — money settings, transaction/report access, service
   availability, and countersign request creation without self-approval.

Include money-relevant cross-role direct URL/API denials. An audience is not
expected to exercise a workflow it cannot reach; verify and record the actual
backend boundary. Keep other general-purpose modules out unless needed to
complete or safely constrain a money workflow.

## Invariants to verify against the current implementation

Reconcile and update these from `AGENTS.md`, current code, current schema, and
the live baseline before freezing cases:

- **Ledger and balance:** account balances reconcile to the active runtime
  ledger rules; pending actions do not move money unless current documented
  workflow explicitly performs and records an immediate cash posting.
- **No wallet settlement:** a loan payment is funded externally and recorded
  to the cooperative; it never debits member savings. Loan proceeds default
  to physical cash. Posting proceeds to savings requires explicit selection
  and confirmation, with delivery method, operator, and transaction linked.
- **Exact arithmetic:** amount, fees, interest, allocations, penalties, and
  remaining principal reconcile to the cent under current rounding rules.
- **Exactly once:** duplicate submission, retry, re-approval, reversal, batch,
  and concurrent/stale actions cannot post a second effect.
- **Lifecycle and correction:** approval/rejection/reversal/edit/closure is
  valid only from the allowed state; reversals are paired and symmetric;
  pending countersign requests do not prematurely apply privileged changes.
- **Separation of duties:** requester cannot approve own privileged action;
  OTP and eligible second approver requirements are enforced server side.
- **Scope:** member and branch data remain isolated as required by active
  permissions and `BranchScope`.
- **Reporting and display:** reports and SPA figures agree with independently
  recomputed persisted values, including reversal, date, and branch filters.
- **Financial settings:** settings and interest batch eligibility/formula,
  period idempotency, and restoration are verified without leaving altered
  global state.

Do not assume older invariant identifiers or formulas are still accurate.
Record any changed rule and its code/schema basis in the role matrix and run
report before execution.

## Evidence method

For every write-capable or rejected mutation case, record the narrowest
pre-action baseline for the target QA records and relevant balances/statuses;
capture the visible form/confirmation and relevant HTTP method/status (sanitize
payloads); perform one action; verify browser result; capture post-action state;
compare persisted values with an independent calculation and the invariant;
then clean up through the application when safe. Keep scoped identifiers and
before/after/diff files under that role’s `evidence/`. For read-only cases,
compare UI and network result with a scoped read-only database calculation.

Review existing `docs/QA/money_correctness_qa/_tools/` scripts for correctness
before use. Never copy stale baseline output as a current measurement. Never
dump unrelated member data. Do not expose credentials, OTPs, cookies, tokens,
or unrelated personal financial details in saved evidence.

## Execution order and gates

1. Confirm repository/branch/commit/dirty state, app and Playwright MCP
   availability, no active Agy process, and local sample database identity
   without printing secrets. If target identity is unclear, allow only
   non-mutating browser cases and mark all writes `BLOCKED`.
2. Capture a minimal current baseline for only planned records, settings,
   balances, transactions, loans, expenses, batches, and countersign state.
   Do not mutate or use legacy pending requests for approval.
3. Before each role browser session, prepare and review its frozen matrix using
   `CASE_MATRIX_TEMPLATE.md`. Check current `PermissionCatalog`, role seeder,
   SPA routes/gates, backend routes/controllers/policies/services, and role DFD.
   Every case defines preconditions/data, steps, UI, network boundary,
   persisted state/no-write, invariant, and cleanup. Mark it `READY` and update
   the board/checkpoint before Agy dispatch.
4. Dispatch one fresh Agy session with only the current role’s approved matrix
   and output path `roles/<role>/`. Use the documented `agy` CLI mode; never
   guess flags or use permission-bypass flags. Agy uses Playwright MCP against
   the running app.
5. Prefer, in order: read-only reconciliation; low-risk invalid-input/no-write
   checks against scoped test records; reversible member requests; controlled
   approval and ledger effects; corrections/reversals; high-impact batch or
   global-setting cases last and only when independently reversible. One
   state-mutating role/browser action at a time.
6. At role closeout, reconcile every planned case/process group, independently
   review failures/partials, inspect evidence and data deltas, clean up, sign
   out and verify session invalidation, end Agy, confirm no Agy process remains,
   and update `RUN_BOARD.md` plus `CHECKPOINT.md`. Only then start the next role.
7. After all seven roles, review all artifacts and data cleanup; write
   `FINAL_REPORT.md` with exact coverage, findings and severity, verified
   invariants, limitations, evidence, changed data, and unresolved risks.

## High-risk probes that require disposable scoped state

Do not test a charge, withdrawal, loan payout, approval, reversal, deletion,
interest batch, or any action that can affect other members against seeded
records or real member records. Use a clearly labeled QA record and a minimal
amount only after confirming the database is the authorized local sample
database. Verify FK/audit effects before any direct DB cleanup. Never use
direct DB editing to force a workflow result. If safe isolation and reversible
cleanup are not available, mark the case `NOT-RUN` with the reason.

Never debit savings to pay a member’s own loan, initiate live Xendit/provider
payments, send real SMS/email, execute backup restore, approve/delete unrelated
records, bypass OTP/countersign, reset/truncate/reseed the database, or leave
global settings/service switches changed. Restore settings in a guaranteed
cleanup path and verify their captured before-values.

Unusual inputs should include, where safely scoped: empty/malformed values,
negative/zero/over-limit amounts, excessive decimals, duplicate/double submit,
stale state, invalid status/order, missing/null fields, and safe cancellation
or refresh. Verify rejected inputs produce no persistence delta. Do not trigger
external side effects.

## Completion criteria

- All seven role matrices were reviewed before their respective browser runs;
  every applicable process maps to cases or an explicit exclusion.
- Every case is `PASS`, `FAIL`, `PARTIAL`, `BLOCKED`, `NOT-RUN`, or `N/A` with
  evidence or a reason; results reconcile with the planned matrix.
- Every money-mutating claim is supported by pre/post persisted state and an
  independent arithmetic check. Rejected actions have a no-write check.
- Every finding is adjudicated by Codex against matrix, browser/network/log,
  implementation, and persisted evidence; unsafe cases are corroborated
  read-only instead of repeated.
- All data/settings changes are restored or precisely itemized; all role
  sessions are invalidated and Agy processes ended.
- Final report states uncovered scope and environment limitations explicitly.
  Do not claim the entire system is defect-free.
