# <Role> money and transaction case matrix — `<run-id>`

- Role key:
- Prepared/reviewed date:
- Branch/commit and dirty state:
- Database identity confirmed (type/name only):
- Current invariant/source review:
- Matrix status: `DRAFT` / `READY` / `COMPLETE`

## Source and authorization review

| Functionality / DFD source and process IDs | Current permission/UI gate | Backend route/controller/service | Expected behavior and drift |
|---|---|---|---|

## Cases

IDs are stable within the run: `AGY-MONEY-<ROLE>-NNN`. Assign IDs before
browser execution; never silently drop or repurpose a case.

| Case ID | Workflow / invariant | Preconditions and scoped QA data | Steps / inputs | Expected UI | Expected API / boundary | Expected persisted state or no-write | Cleanup / invariant | Status / evidence |
|---|---|---|---|---|---|---|---|---|

Statuses: `PASS`, `FAIL`, `PARTIAL`, `BLOCKED`, `NOT-RUN`, `N/A`.

## Coverage and review gate

| Workflow / process group | Applicable? | Case IDs | Outcome | Evidence or exclusion reason |
|---|---|---|---|---|

- Money workflows mapped to the role DFD and functionality requirements:
- Effective permission checked in catalog/seeder and UI:
- Backend route/controller authorization checked:
- Network and persisted-state expectations are concrete:
- QA records are isolated, reversible, and cleanup is defined:
- Global settings/balances to snapshot are listed:
- Matrix frozen before Agy session:
- Post-dispatch additions and reason:

## Uncovered or unsafe scope

List money workflows that cannot be safely or reliably run and the precise
reason. Mark them `BLOCKED` or `NOT-RUN` in results; do not count them as pass.
