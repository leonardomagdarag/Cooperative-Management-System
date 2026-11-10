# AGENTS.md

Cooperative management system for **Settlers Credit Cooperative**. Laravel 12 monolith (PHP 8.4) serving a **React 19 SPA** built with Vite and Tailwind CSS, session-authenticated with Sanctum, permissions via Spatie. The local development database is MySQL; feature tests force SQLite in memory. No Blade page-per-view — the entire UI is one React app, the sole exception being the standalone maintenance page (see Architecture).

## Current status (2026-11-05)

- This is an actively maintained system with broad member, officer, financial, support, and administration workflows. Treat the functionality list, code, and current authorization as the source of truth; “final” does not mean defect-free or production-certified.
- The latest documented role audit is `AGY-QA-2026-10` (2026-10-25 to 2026-10-26). It completed its stated Guest, Member, Loan Officer, Accountant, Manager, Chairman, and Administrator scope and reported one High frontend defect: `SettingsTabHeader is not defined` on the default Settings view, reproduced for six authenticated roles. See `docs/QA/agy_system_audit/FINAL_REPORT.md` and `FINDINGS_SUMMARY.md`.
- In the current checkout, `resources/react/pages/settings/SettingsPage.tsx` imports `SettingsTabHeader`. This addresses the reported missing-import cause in source, but the audit documents no post-change browser retest. Do not label the finding verified fixed until the affected roles and account settings workflows have been retested in the running app; update the QA register and evidence then.
- The audit was scoped, local, and Chromium-only. It did not exhaust custom roles, every lifecycle state, provider integrations, concurrency, or irreversible workflows. It reported no QA writes left behind and unchanged scoped record counts; that is not a full database diff or a production readiness claim.
- Earlier QA reports and their “all tests passed” sign-offs are historical snapshots. Consult the latest audit and finding register before relying on their statuses. Manual QA and the automated Pest/Playwright suites provide different evidence and neither alone proves the whole system defect-free.

## Architecture

- **This system is not, and must not become, a digital wallet.** Cooperative account
  balances are member ledger records, not stored-value funds members can use to pay
  loans or make third-party purchases. Loan payments must be funded externally and
  recorded as payments to the cooperative; never debit a member's savings account
  to settle their own loan. Loan proceeds are normally paid to the member as
  physical cash. A disbursement may instead be explicitly posted to the member's
  savings ledger when the officer selects that option and confirms it. Keep the
  selected delivery method, operator, and transaction linked in the audit trail.
  Do not add wallet transfers, wallet top-ups, or wallet-style payment flows.

- **One Blade shell, everything else is React.** `resources/views/react.blade.php` renders `<div id="app">` and mounts `resources/react/main.tsx`. All routing is client-side in `resources/react/App.tsx` (react-router). `app/Http/Controllers/PageController.php` just returns that view.
- **`routes/web.php` ends with a catch-all** — `Route::get('/{path?}', ...)->name('react.spa')` with `->where('path', '.*')`. It **must stay the last route**; any GET route appended after it is silently shadowed and will 404 into the SPA.
- **`routes/api.php` is inside the `web` middleware group, not `api`.** Auth is cookie/session based, so protected routes are declared `Route::middleware(['web', 'auth:web', 'otp.verified', 'verified'])`; a second group omits `verified` for pre-verification OTP flows. There are no API tokens.
- **Vite proxies a hardcoded allowlist of paths to `http://127.0.0.1:8000`** (`vite.config.js`). A new top-level web path used by the SPA must be added to that proxy list or it will not reach Laravel in dev.
- **Layered auth gates**, aliases registered in `bootstrap/app.php`: `auth` → `otp.verified` → `verified` → `profile.completed` → `service.availability:<deposit|withdrawal|loan_application|loan_payment>` (note the **underscores** in the last two). New service endpoints must sit inside the protected group *and* carry `profile.completed` + `service.availability:*` or members can bypass onboarding/maintenance.
- **HTTP 423 is overloaded**: `EnsureOtpVerified` (redirect to `/security/verify-otp`) and `CheckServiceAvailability` (service under maintenance) both return it. `resources/react/api/client.ts` intercepts 423 and hard-redirects — be careful adding new 423 semantics.
- `resources/react/api/client.ts` is the axios instance: sends `XSRF-TOKEN` cookie as `X-XSRF-TOKEN`, reloads on 419, redirects on 423. Use it instead of raw `axios`.
- `resources/react/pages/**` is organized by feature; route → component mapping and per-section permission gates live in `DashboardRouter` inside `App.tsx`, not in a router config file.
- **The maintenance page is the one deliberate exception to "everything is React".** `resources/views/maintenance.blade.php` is standalone inline-CSS Blade with no Vite tag, no session, and no database reads — it has to render when the database is the thing that is broken, so it must never grow a dependency on one. `Route::get('/maintenance')` in `routes/web.php` is declared **before** the catch-all and is also in the Vite proxy list.
- **Maintenance state is a file, not cache.** `app/Support/MaintenanceState.php` stores `storage/app/maintenance-state.json` because a restore replaces the database that the cache and `SystemSetting` tables live in. `BackupService::restore()` brackets Laravel `down`/`up` with `enter`/`leave`; the nested `finally` is deliberate, so a failing `up` cannot strand a notice that outlives the outage. Entries older than the staleness window are treated as debris. `resources/views/errors/503.blade.php` renders the same view for Laravel maintenance mode, which is what serves *every* route during a restore — `/maintenance` is never reached then. Keep the existing `--secret` bypass in `Artisan::call('down', ...)`.
- **Sign-in switches are enforced in three places**, all of which matter: `EnsurePublicLoginEnabled` guards `GET /login` (alias `public.login.enabled`), `Api\AuthController::login` guards the API (it historically missed the *global* switch entirely), and the per-audience switches decide which copy the page shows. `MaintenanceState::syncLoginAvailability()` re-derives the reason from `SystemSetting` rather than trusting the recorded file, and must be called wherever a stale notice would be misleading.
- **Privileged actions never mutate directly — they go through the countersign workflow.** `app/Services/CountersignService.php` records a `PrivilegedActionRequest`, requires a second approver (active chairman holding `oversight.approve`) plus OTP, then executes via the `switch ($actionType)` in `performAction()`. Role/permission changes, admin create/delete/restore, backup restore (manual backup delete is permanently disabled), settings and collection-workflow updates already use it, as do the Financial Accounts actions `financial.settings.update` and `financial.interest.run`. A new action type must add a `case` there or `request()` throws `Unknown privileged action type`; approvals run through the `/api/oversight/requests*` routes (`OversightController`).

## Roles & permissions

- The current built-in officer role presets are `admin`, `chairman`, `manager`, `accountant`, and `loan`; members use `member`. Custom/dynamic roles are supported. `secretary` and `treasurer` are legacy names migrated to `accountant`, not separate active roles. The latest documented role audit is `docs/QA/agy_system_audit/FINAL_REPORT.md`; see also `docs/QA/QA_LOG.md` and `docs/QA/QA_RESULT_1.md` for historical findings. A passing test-case count does not mean the run found no defects.
- **`app/Support/PermissionCatalog.php` is the single source of truth** for permission names (`groups()`), role presets (`rolePresets()`), and officer roles (`officerRoles()`). Add a permission there, not inline in a controller.
- **`officerRoles()` is `[chairman, manager, accountant, loan]` — it excludes `admin` and `developer`.** Code that gates on `hasAnyRole(PermissionCatalog::officerRoles())` must check `admin` explicitly too (see `CheckServiceAvailability`), or admin users get treated as members.
- Legacy role names are migrated, not supported: `secretary` and `treasurer` become `accountant` (migration `2026_10_03_224440_*` and `SpatieRolePermissionSeeder`). Do not reintroduce them.
- `User::primaryRole` (`app/Models/User.php`) is an **accessor** that returns the first Spatie role name, falling back to the `role` column. The `role` column is a plain string — custom/dynamic roles are supported and are not an enum.
- **`User::can()` is overridden** to layer `OfficerPermissionOverride` (per-officer allow/disallow) on top of Spatie. Authorize with `$user->can(...)` / `abort_unless(...)`, never by comparing role strings.
- The effective permission array is computed **server-side** in `Api\AuthController::user()` and shipped to the SPA. Frontend `PermissionGate` in `App.tsx` and the `permission` keys in `resources/react/components/Sidebar.tsx` are UX only — backend `can()` is the real gate.
- **`config/sidebar.php` is dead config** — nothing reads it. Nav visibility lives in the hardcoded `items` array in `resources/react/components/Sidebar.tsx`. Editing the config file has no effect.
- Branch isolation is centralized in `app/Support/BranchScope.php`. Use its `applyTo*` helpers for any query over `users`, `transactions`, `loans`, or `expenses`; `branches.bypass` / `admin` / `developer` / `branch_id === null` are unrestricted.
- Audit writes go through `app/Services/AuditLogService.php` (spatie activitylog, 365-day retention). Use it for state-changing actions rather than ad-hoc logging.

## Commands

```bash
composer dev        # serve + queue:listen(backups,emails,default) + pail + vite, via concurrently
composer test       # config:clear, then php -d memory_limit=512M artisan test
npm run dev         # vite only (expects php artisan serve on :8000)
npm run build       # production assets -> public/build
vendor/bin/pint     # formatter, laravel preset (no pint.json in repo)
```

- Scheduler (`php artisan schedule:run` every minute) must be running. `backup:ping` reads per-tier cron expressions from `SystemSetting` rows and only runs what is due, and a scheduled `queue:work --queue=default,emails,backups --stop-when-empty` drains the queue (shared hosting has no persistent worker). `composer dev` runs its own `queue:listen` on the same three queues. See `docs/system details/BACKUPS.md`.

## Testing

- **PHP: Pest.** `tests/Pest.php` binds `RefreshDatabase` **only to `Feature`** — Unit tests do not get a fresh DB.
- Test env is forced to sqlite `:memory:` in `phpunit.xml` regardless of `.env`, with `QUEUE_CONNECTION=sync` and `MAIL_MAILER=array`.
- Single test: `php artisan test --filter=BranchApiTest` (also matches case names). Single suite: `--testsuite=Feature`. Single E2E spec: `npx playwright test tests/e2e/03-member-services.spec.ts`.
- **Feature tests do not auto-seed.** The established pattern (used by ~120 test files) is:
  ```php
  beforeEach(function () {
      $this->seed(SpatieRolePermissionSeeder::class);
      $this->seed(UserSeeder::class);
      $this->admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();
  });
  ```
  `DatabaseSeeder` runs `SpatieRolePermissionSeeder` **twice** (once to create roles, once after `UserSeeder` to backfill) — if you add seeded users, keep that two-pass ordering.
- Local seeded logins are all `password`: officers use `leo.baluyot039+{role}@gmail.com` (e.g. `leo.baluyot039+admin@gmail.com`), members use `leo.baluyot039+{first}.{last}@gmail.com` (e.g. `leo.baluyot039+arjay.ramos@gmail.com`) — except the one real-gmail member `leo.baluyot.magdarag039@gmail.com`. The Namecheap officer emails and seeders intentionally differ; see "Namecheap email" below. `developer@example.com` is the local all-access testing account: the `developer` role is built from `PermissionCatalog::allPermissions()` minus the two maker-checker permissions (`oversight.approve`, `transactions.approve-own`), with no `Gate::before` superuser bypass. Delete the account (and the `developer` preset) when testing is finished.
- **E2E: Playwright**, `tests/e2e/*.spec.ts`, `npx playwright test`. `workers: 1` and `fullyParallel: false` are deliberate — the specs mutate a **real shared MySQL database** and will conflict if parallelized. 90s timeout, 1 retry.
- E2E prerequisites: `php artisan serve` on `127.0.0.1:8000`, assets built (`npm run build`) or Vite running, and a **seeded MySQL** database. `tests/e2e/helpers/auth.ts` reads one-time OTPs out of `storage/logs/laravel.log`, so logging must be capturing them.
- No ESLint, no `tsc`, no frontend test runner. Verification order: `vendor/bin/pint --test` → `php artisan test` → `npm run build`.

## Manual QA

- The initial manual QA pass has covered all system roles. Follow-up QA is a cleanup pass: use the first-pass findings as leads, reproduce them in the running application, verify expected behavior, and update the findings with confirmed, fixed, or not-reproducible status.
- Test as the role named in the task and follow its real user workflows through the browser. Do not rely on the existing Playwright E2E specs for this manual QA pass; they are deprecated for this purpose. Read relevant project documentation and functionality notes before testing, and inspect application code, database records, or logs when needed to understand observed behavior.
- Do not change application behavior or backend/controller logic as part of a QA-only task. Record reproducible defects with evidence and a proposed fix for a separate implementation task. If the task explicitly includes fixing defects, follow the repository architecture and security instructions above.
- Protect the database. Use only task-scoped test data and make reversible changes; record any records changed or created. Do not reset, truncate, or otherwise wipe the database unless the task explicitly authorizes it and the target data is confirmed to be disposable. Clean up QA-created data when safe and practical.
- Save screenshots and the final report under a clearly named, role- or feature-specific folder in `docs/`, with descriptive filenames. For each finding, record the role/workflow, reproduction steps, expected and actual results, status, and supporting screenshot or relevant error/log evidence. Avoid capturing credentials, OTPs, or other secrets in screenshots or reports.
- Close the pass with a concise summary of verified findings, evidence locations, any test data left behind, and unresolved issues. Do not mark an issue fixed without verifying the corrected behavior in the running application.

## Environment gotchas

### Namecheap email (2026-11-10)

- The production app at `settlerscc.online` sends SMTP mail from the `system@settlerscc.online` mailbox. Officer login and contact emails in the production database are `admin@`, `manager@`, `accountant@`, and `chairman@settlerscc.online`. Each officer address forwards incoming mail to the matching `leo.baluyot039+{role}@gmail.com` address. These are the configured destinations for notifications, OTPs, and password reset messages; officers sign in with their cooperative domain addresses. The four forwarders are configured in Namecheap, not in Laravel. `system@` is for the app's outgoing SMTP and does not forward officer mail. A test message was accepted by SMTP, but receipt was not confirmed in the connected Gmail account; verify with the destination mailbox before claiming end-to-end delivery.
- The Namecheap checkout at `/home/leonliit/settlerscc.online` has **production-only seeder edits** in `database/seeders/UserSeeder.php`, `ChairmanSeeder.php`, `MemberApplicationSeeder.php`, `LeoLoanDueTestSeeder.php`, and `QaChairmanSeeder.php`. They use the four `@settlerscc.online` officer addresses so reseeding reuses the existing accounts. Member sample addresses remain Gmail. These edits were made on Namecheap only at the user's request; this local checkout and its tests still use the Gmail officer fixtures.
- The Namecheap seeder edits are uncommitted deployment differences. Before pushing or deploying local code to Namecheap, preserve or deliberately reconcile them; overwriting those files with the local Gmail versions would make a later reseed create duplicate officer accounts. Never commit mailbox passwords or production `.env` values.

- **`.env` is gitignored — a fresh clone has none.** Bootstrap with `cp .env.example .env && php artisan key:generate`. This checkout's `.env` is a local dev config (`APP_ENV=testing`, local MySQL, `DB_DUMP_BINARY_PATH` for Laragon) holding real credentials; `.env.example.dev` / `.env.example.prod` are local-only and gitignored for carrying credentials too. Never commit any of them.
- If `php artisan test` fails with `Command "test" is not defined`, or `vendor/bin/pint` is missing, dev dependencies are absent: run `composer install`.
- `public/storage` is not linked — `php artisan storage:link` is required for uploaded images to resolve.
- Compiled Blade views are cached in this checkout (`storage/framework/views/`); run `php artisan view:clear` when Blade changes appear to be ignored.
- Migrations are hand-named and **share duplicate timestamp prefixes** (two `2026_09_09_000001_*` files, two `2026_09_09_000002_*`). Ordering resolves by full filename; do not renumber existing files.
- `DB_DUMP_BINARY_PATH` must be set in `.env` for database-scope backups on Windows/Laragon.
- **A fresh worktree/clone needs `storage/framework/views/` to exist.** The `.gitignore` that keeps that directory alive is tracked, but the directory itself is not committed, so a `git worktree add` will not create it and every Blade render dies with `RuntimeException: View path not found.` If you ever delete that `.gitignore`, restore it. Same applies to any other empty-but-required storage dir.
- **If a fresh worktree 500s on every page, suspect `.env`** (missing `APP_KEY`, bad credentials) rather than stale caches — `bootstrap/cache/*` and `storage/framework/views/*` are gitignored, so a new worktree starts clean.
- **`env()`'s default argument only fires when a key is *missing*, not when it is present-but-empty.** Any `env('X', 'fallback')` therefore yields `''` if `.env` contains a bare `X=`. Use `?:` (`env('X') ?: 'fallback'`) when empty must also fall through — and never `||`, which returns a bool and will poison string configs. See `config/backup.php` and the header of `.env.example`.

## Docs & conventions

- **`FUNCTIONALIT_LIST.md` (repo root) is the system-functionality reference** for workflows and role capabilities; `app/Support/PermissionCatalog.php`, seeders, and active backend authorization remain authoritative for actual access. Read the functionality reference before changing access rules, then verify expectations against code.
- `docs/QA/CODE_DOCUMENT_ALIGNMENT.md` records known code/documentation drift; `docs/QA/QA_RESULT_1.md`, `docs/QA/QA_LOG.md`, and role-specific reports under `docs/QA/` hold browser QA findings and their current statuses. Check the latest register before repeating or closing a finding.
- `docs/system details/` is the home for documents about the system, including backup and database references, data-flow inventories, audits, research, capstone materials, and diagrams. Read the relevant references before changing system behavior; verify working notes and audit conclusions against current code.
- Rework notes live under `docs/system details/reworks/` (including collections, roles, ledger/OTP, transaction approval, financial accounts, and SMS fallback). They are orientation only, not authoritative behavior specifications.
- System diagrams and their generation tools live under `docs/system details/diagrams/`. Local QA captures, scratch files, and audit runner artifacts live under `docs/QA/local artifacts/`; these are not authoritative system behavior documentation.
- `README.md` references a `setup.sh` that does not exist in this repo. Ignore it.
- Commit messages use Conventional Commits: `feat(auth):`, `fix(loans):`, `chore(gitignore):`, `test(audit):`.
- There is no `opencode.json`, CI workflow, or pre-commit hook. Nothing runs verification automatically.
