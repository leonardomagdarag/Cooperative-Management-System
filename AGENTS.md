# AGENTS.md

Cooperative management system for **Settlers Credit Cooperative**. Laravel 12 monolith (PHP 8.4) serving a **React 19 SPA**, session-authenticated with Sanctum, permissions via Spatie. No Blade page-per-view — the entire UI is one React app, the sole exception being the standalone maintenance page (see Architecture).

## Architecture

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
- **Privileged actions never mutate directly — they go through the countersign workflow.** `app/Services/CountersignService.php` records a `PrivilegedActionRequest`, requires a second approver (active chairman holding `oversight.approve`) plus OTP, then executes via the `switch ($actionType)` in `performAction()`. Role/permission changes, admin create/delete/restore, backup delete/restore, settings and collection-workflow updates already use it. A new action type must add a `case` there or `request()` throws `Unknown privileged action type`; approvals run through the `/api/oversight/requests*` routes (`OversightController`).

## Roles & permissions

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

- Scheduler (`php artisan schedule:run` every minute) must be running. `backup:ping` reads per-tier cron expressions from `SystemSetting` rows and only runs what is due, and a scheduled `queue:work --queue=default,emails,backups --stop-when-empty` drains the queue (shared hosting has no persistent worker). `composer dev` runs its own `queue:listen` on the same three queues. See `docs/BACKUPS.md`.

## Testing

- **PHP: Pest.** `tests/Pest.php` binds `RefreshDatabase` **only to `Feature`** — Unit tests do not get a fresh DB.
- Test env is forced to sqlite `:memory:` in `phpunit.xml` regardless of `.env`, with `QUEUE_CONNECTION=sync` and `MAIL_MAILER=array`.
- Single test: `php artisan test --filter=BranchApiTest` (also matches case names). Single suite: `--testsuite=Feature`. Single E2E spec: `npx playwright test tests/e2e/03-member-services.spec.ts`.
- **Feature tests do not auto-seed.** The established pattern (used by ~120 test files) is:
  ```php
  beforeEach(function () {
      $this->seed(SpatieRolePermissionSeeder::class);
      $this->seed(UserSeeder::class);
      $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
  });
  ```
  `DatabaseSeeder` runs `SpatieRolePermissionSeeder` **twice** (once to create roles, once after `UserSeeder` to backfill) — if you add seeded users, keep that two-pass ordering.
- Seeded logins are all `password`: officers `admin@`, `manager@`, `accountant@`, `loan@example.com` (emails are `{role}@example.com`), `developer@example.com`, and members as `first.last@example.com` (e.g. `arjay.ramos@example.com`).
- **E2E: Playwright**, `tests/e2e/*.spec.ts`, `npx playwright test`. `workers: 1` and `fullyParallel: false` are deliberate — the specs mutate a **real shared MySQL database** and will conflict if parallelized. 90s timeout, 1 retry.
- E2E prerequisites: `php artisan serve` on `127.0.0.1:8000`, assets built (`npm run build`) or Vite running, and a **seeded MySQL** database. `tests/e2e/helpers/auth.ts` reads one-time OTPs out of `storage/logs/laravel.log`, so logging must be capturing them.
- No ESLint, no `tsc`, no frontend test runner. Verification order: `vendor/bin/pint --test` → `php artisan test` → `npm run build`.

## Environment gotchas

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

- **`FUNCTIONALIT_LIST.md` (repo root) is the verified system-functionality reference** — roles, per-role capabilities, and the exact permission gating in use. Read it before changing access rules.
- `docs/BACKUPS.md` covers the three backup tiers, B2 off-site setup, restore safety, and ops commands; `docs/DATABASE_DICTIONARY.csv` documents table/column meanings when changing schema.
- Root `*_REWORK.md` files (`COLLECTIONS_REWORK`, `ROLE_REWORK`, `LEDGER_OTP_REWORK`, `TRANSACTION_APPROVAL_REWORK`, `REWORK_IMPLEMENTATION_SUMMARY`) are uncommitted working notes for the current rework — orientation only, verify against code.
- `Leo/` (gitignored; only `Leo/audit_pipeline.ps1` is tracked) and the untracked `scratch/` hold planning/audit material. Not authoritative.
- `README.md` references a `setup.sh` that does not exist in this repo. Ignore it.
- Commit messages use Conventional Commits: `feat(auth):`, `fix(loans):`, `chore(gitignore):`, `test(audit):`.
- There is no `opencode.json`, CI workflow, or pre-commit hook. Nothing runs verification automatically.
