# Agy money and transaction audit

Preparation packet for a fresh, current manual browser audit focused on money
movement, transaction lifecycle, accounting, and financial displays. The audit
uses Antigravity (`agy`) with Playwright MCP, one fresh Agy session per audience,
in the sequence Guest, Member, Loan Officer, Accountant, Manager, Chairman,
Administrator.

## Start here

In a new Codex session at the repository root, provide
[`ORCHESTRATION_PROMPT.md`](ORCHESTRATION_PROMPT.md). The Codex orchestrator
must continue through all seven roles and final synthesis. It must not stop
after planning, a role, or a token refill. Each role's reviewed case matrix is
a gate before Agy starts.

## Packet files

- [`PLAN.md`](PLAN.md): scope, invariants, execution order, proof and safety.
- [`ORCHESTRATION_PROMPT.md`](ORCHESTRATION_PROMPT.md): instructions for the
  fresh Codex run, including checkpointing and Agy token refill waits.
- [`CASE_MATRIX_TEMPLATE.md`](CASE_MATRIX_TEMPLATE.md): role-specific cases
  with UI, network, persistence, invariant, and cleanup expectations.
- [`RUN_BOARD.md`](RUN_BOARD.md): sequential role status and run metadata.
- [`CHECKPOINT.md`](CHECKPOINT.md): durable resume point; update before every
  wait, interruption, or role transition.
- `roles/<role>/`: frozen matrix, report, findings, activity log, and sanitized
  evidence for each role.
- `FINAL_REPORT.md`: final reviewed synthesis.

## Prior work is leads only

Use `docs/QA/money_correctness_qa/` as historical context: especially its
invariants, system map, test matrix, findings register, read-only measurement
tools, and October 22 run artifacts. Do not reuse its baseline, assert its
findings are current, or copy its expected results without checking the current
routes, permissions, controllers/services, models, migrations, frontend, and
live sample data. Existing QA records may be inspected read-only; do not use
them as disposable data or approve old pending requests.

This packet plans a QA run; it does not authorize code changes, live provider
transactions, database resets, or unsafe financial mutations. The user has
authorized scoped, reversible QA records on the local sample database after
its identity is confirmed.
