# Fresh Codex session prompt — Agy money and transaction audit

Copy this prompt into a new Codex session opened at the repository root. This
is a run packet, not permission to change application code. Continue through
all seven roles and final synthesis; do not stop after planning or one role.

## Objective

Execute the current, evidence-backed manual browser audit described in
[`PLAN.md`](PLAN.md), focused on transactions and money-related services. Use
Antigravity (`agy`) with Playwright MCP, one fresh Agy session per role, in
this exact order: Guest, Member, Loan Officer, Accountant, Manager, Chairman,
Administrator. Review each role’s outputs, end its Agy session, confirm it is
inactive, and only then proceed.

QA only. Do not change application code, claim a fix, or treat historical QA as
proof. Use the current backend and live persisted state as the access and money
behavior oracles. Produce the final report even when some cases are blocked.

## Required preparation

Read `AGENTS.md`, relevant money sections of `FUNCTIONALIT_LIST.md`,
`docs/QA/QA_LOG.md`, `docs/QA/CODE_DOCUMENT_ALIGNMENT.md`, the money-related
DFD inventories/master process flow/sidebar process map and role verification
maps, this packet, then the relevant material in
`docs/QA/money_correctness_qa/` including the latest run artifacts. Inspect
current permissions/seeders, frontend route gates, backend routes/controllers/
services/models/migrations, and read-only measurement scripts relevant to
planned cases. Historical matrices, findings, data, and October 22 measurements
are leads only; verify each against the current checkout.

Before browser actions, confirm repository root, branch/commit, dirty working
tree, app availability, Playwright MCP availability through Agy, no prior Agy
session/process, and database identity. Never print `.env` or secrets. Do not
change configuration or point the app at another database. Confirm it is the
authorized local sample database. If identity is unclear, perform only safe
read-only browser checks; mark mutations blocked. Capture a minimal baseline
for only planned QA records, relevant balances/transactions/loans/expenses,
financial settings, interest batches, and pending countersign requests.

## Matrix gate and role execution

Before opening a browser for each role, create/review its matrix from
`CASE_MATRIX_TEMPLATE.md`, role DFD, functionality requirements, and current
authorization implementation. Every case must define the expected UI,
network/API boundary, persisted result or explicit no-write assertion,
invariant, scoped test data, and cleanup. Check `PermissionCatalog`, role
seeder, frontend gate, and backend route/controller/policy/service. Mark the
matrix `READY`; update the run board and checkpoint before dispatch. Do not
dispatch an unreviewed matrix.

Start a fresh Agy session scoped only to the role, its frozen matrix, and
`roles/<role>/` output. Use supported `agy --help`/project instructions to
choose the CLI mode; do not guess flags and never use permission-bypass flags.
Use Playwright MCP against the running SPA. Do not use deprecated Playwright
E2E specs as manual QA proof.

Exercise current applicable money workflows: member deposits/withdrawals and
loan services; external loan repayment; loan review/schedule/disbursement and
cash-vs-explicit-savings delivery; transaction approvals and reversals;
collections; ledger and receipts; expense flows; interest and financial
settings; financial reports and displayed balances; idempotency, branch/member
scope, and separation of duties. Allocate each workflow to roles according to
current backend permission. Verify role denials through safe direct API/URL
checks. Use the matrix’s order: read-only reconciliation first, safe invalid
inputs/no-write next, reversible scoped mutations next, corrections and
countersign paths after those, and broad/batch/global-setting actions last.

For every mutation, bracket the action with scoped before/after persisted
measurements and compare with an independent calculation. Verify UI, response
status, stored state, linked audit/operator/delivery fields, and no duplicate
effects as applicable. Rejected inputs must show no unintended write. Use only
disposable, labeled QA records; do not test writes against seeded member data.
If safe reversible setup is not possible, record `NOT-RUN` and why. Use the
browser workflow for cleanup where possible; direct DB cleanup only after
inspecting the exact row and FK/audit effects and using the narrowest
reversible operation.

At each role closeout, reconcile every matrix/process group; independently
review Agy `FAIL`/`PARTIAL` observations and proposed findings; capture
sanitized evidence; restore scoped data/settings or itemize anything left;
sign out and verify session invalidation; end Agy and confirm no session is
running. Update `RUN_BOARD.md` and `CHECKPOINT.md` before the next role.

## Continuity and Agy token refill handling

This session must continue until all seven role closeouts, finding review, and
`FINAL_REPORT.md` are complete. Do not treat Agy token exhaustion, a temporary
rate limit, a long-running case, or an incomplete role as the end of the run.

If Agy reports depleted tokens or a refill wait:

1. Stop new browser mutations. Save its last completed case and exact next
   action. Persist `CHECKPOINT.md` with role, case, matrix status, Agy session
   and process status, last artifact paths, current scoped DB/settings deltas,
   cleanup state, and the refill ETA/time source. Update `RUN_BOARD.md` to
   `WAITING`. Never record credentials, token values, OTPs, or session secrets.
2. If Agy is still active, check whether it is waiting for input or has exited;
   do not start another Agy session concurrently. If the Agy process ended,
   record that fact. Do not discard the current role matrix or partially
   completed evidence.
3. Wait in interruptible intervals of at most 60 seconds using the available
   clock sleep, checking for user steering and only checking Agy/runtime state
   when useful. Base the total wait on the refill ETA reported by Agy or
   available rate-limit metadata; do not invent an ETA. The wait for any one
   refill is capped at 3 hours. If ETA is longer or absent, wait at most 3
   hours before rechecking availability; continue with another bounded wait
   only if still unavailable and the user has not redirected the task.
4. When tokens are available and the old Agy session is no longer active,
   resume the same role in a fresh Agy session using the saved matrix and
   checkpoint. Review its existing artifacts first and continue at the exact
   next case; do not repeat completed writes. Reconfirm current DB state before
   resuming any mutation. Update the checkpoint and board before dispatch.
5. Do not advance to the next role until the current role is reconciled,
   cleaned up or itemized, signed out, reviewed, and its Agy session is ended.

Routine Agy progress checks should be concise and spaced about three minutes
apart. Wait no longer than 60 seconds per wait call and check sooner on process
exit, error, blocker, input request, or user message. Persist the checkpoint
before every extended wait or interruption. If the app/browser/database has a
blocker, record it accurately and continue independent safe work; do not
fabricate passes.

## Safety invariants

- The database is not a wallet. Never fund loan repayment by debiting savings.
- Loan proceeds default to physical cash; savings posting requires explicit
  choice and confirmation and must preserve delivery/operator/transaction audit
  linkage.
- Never initiate live provider payments or send real SMS/email; never restore a
  backup, reset/truncate/reseed, or approve/delete unrelated records.
- Privileged actions use the real countersign and OTP flow. Never self-approve
  or bypass a gate. Do not approve old pending requests.
- Restore financial settings/service switches to measured before-values and
  verify after cleanup. Do not leave maintenance/login switches altered.
- Do not expose credentials, OTPs, cookies, tokens, or unrelated financial
  details in reports, screenshots, logs, or API evidence.
- Do not modify application behavior. Findings are observed defects only; a
  UI automation failure is not itself a defect.

## Role artifacts and final reporting

Under `roles/<guest|member|loan|accountant|manager|chairman|admin>/`, create
`CASE_MATRIX.md`, `REPORT.md`, `FINDINGS.md`, `ACTIVITY_LOG.md`, `evidence/`,
and `screenshots/`. Use finding IDs `AGY-MONEY-<ROLE>-NNN`, with severity
Blocker/High/Medium/Low and status New/Confirmed/Fixed & Retested/Could Not
Reproduce/As Designed/Deferred. This run cannot claim `Fixed & Retested`
unless a separate deployed fix was independently verified live.

Codex independently adjudicates every failure/partial against its frozen
expectation, browser/network evidence, current authorization/code path, and
persisted state. Replay a safe issue once from a fresh page/context; corroborate
high-impact money issues read-only rather than repeating an unsafe mutation.
Record uncertainty as `PARTIAL`, `BLOCKED`, or `Could Not Reproduce`.

After all seven roles, write `FINAL_REPORT.md` with executive summary, exact
role/workflow coverage, results and severity, verified invariants, unresolved
risks, environment/provider limits, evidence paths, created/changed records,
and cleanup. Update the run board and checkpoint to complete. Do not claim the
whole system is defect-free. Do not append to the general QA log until the
report and findings have been checked.

## First action

Inspect the required sources and current runtime/environment, establish safe
database identity and a scoped baseline, then prepare/review the Guest money
case matrix. Mark it `READY` and update the board/checkpoint before dispatching
the first fresh Agy session.
