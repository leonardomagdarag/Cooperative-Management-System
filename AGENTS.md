# AGENTS.md

Cooperative management system for **Settlers Credit Cooperative**. Laravel 12 monolith (PHP 8.4) serving a **React 19 SPA**, session-authenticated with Sanctum, permissions via Spatie. No Blade page-per-view — the entire UI is one React app, the sole exception being the standalone maintenance page (see Architecture).

## Architecture

- **One Blade shell, everything else is React.** `resources/views/react.blade.php` renders `<div id="app">` and mounts `resources/react/main.tsx`. All routing is client-side in `resources/react/App.tsx` (react-router). `app/Http/Controllers/PageController.php` just returns that view.
- **`routes/web.php` ends with a catch-all** — `Route::get('/{path?}', ...)->name('react.spa')` with `->where('path', '.*')`. It **must stay the last route**; any GET route appended after it is silently shadowed and will 404 into the SPA.
- **`routes/api.php` is inside the `web` middleware group, not `api`.** Auth is cookie/session based, so routes are declared as `Route::middleware(['web', 'auth:web', 'otp.verified', 'verified'])`. There are no API tokens.
- **Vite proxies a hardcoded allowlist of paths to `http://127.0.0.1:8000`** (`vite.config.js`). A new top-level web path used by the SPA must be added to that proxy list or it will not reach Laravel in dev.
- **Layered auth gates**, aliases registered in `bootstrap/app.php`: `auth` → `otp.verified` → `verified` → `profile.completed` → `service.availability:<deposit|withdrawal|loan_application|loan_payment>` (note the **underscores** in the last two). New service endpoints must sit inside the protected group *and* carry `profile.completed` + `service.availability:*` or members can bypass onboarding/maintenance.
- **HTTP 423 is overloaded**: `EnsureOtpVerified` (redirect to `/security/verify-otp`) and `CheckServiceAvailability` (service under maintenance) both return it. `resources/react/api/client.ts` intercepts 423 and hard-redirects — be careful adding new 423 semantics.
- `resources/react/api/client.ts` is the axios instance: sends `XSRF-TOKEN` cookie as `X-XSRF-TOKEN`, reloads on 419, redirects on 423. Use it instead of raw `axios`.
- `resources/react/pages/**` is organized by feature; route → component mapping and per-section permission gates live in `DashboardRouter` inside `App.tsx`, not in a router config file.
- **The maintenance page is the one deliberate exception to "everything is React".** `resources/views/maintenance.blade.php` is standalone inline-CSS Blade with no Vite tag, no session, and no database reads — it has to render when the database is the thing that is broken, so it must never grow a dependency on one. `Route::get('/maintenance')` in `routes/web.php` is declared **before** the catch-all and is also in the Vite proxy list.
- **Maintenance state is a file, not cache.** `app/Support/MaintenanceState.php` stores `storage/app/maintenance-state.json` because a restore replaces the database that the cache and `SystemSetting` tables live in. `BackupService::restore()` brackets Laravel `down`/`up` with `enter`/`leave`; the nested `finally` is deliberate, so a failing `up` cannot strand a notice that outlives the outage. Entries older than the staleness window are treated as debris. `errors/503.blade.php` renders the same view for Laravel maintenance mode, which is what serves *every* route during a restore — `/maintenance` is never reached then. Keep the existing `--secret` bypass in `Artisan::call('down', ...)`.
- **Sign-in switches are enforced in three places**, all of which matter: `EnsurePublicLoginEnabled` guards `GET /login`, `Api\AuthController::login` guards the API (it historically missed the *global* switch entirely), and the per-audience switches decide which copy the page shows. `MaintenanceState::syncLoginAvailability()` re-derives the reason from `SystemSetting` rather than trusting the recorded file, and must be called wherever a stale notice would be misleading.

## Roles & permissions

- **`app/Support/PermissionCatalog.php` is the single source of truth** for permission names (`groups()`), role presets (`rolePresets()`), and officer roles (`officerRoles()`). Add a permission there, not inline in a controller.
- `User::primaryRole` (`app/Models/User.php`) is an **accessor** that returns the first Spatie role name, falling back to the `role` column. The `role` column is a plain string — custom/dynamic roles are supported and are not an enum.
- **`User::can()` is overridden** to layer `OfficerPermissionOverride` (per-officer allow/disallow) on top of Spatie. Authorize with `$user->can(...)` / `abort_unless(...)`, never by comparing role strings.
- The effective permission array is computed **server-side** in `Api\AuthController::user()` and shipped to the SPA. Frontend `PermissionGate` in `App.tsx` and the `permission` keys in `resources/react/components/Sidebar.tsx` are UX only — backend `can()` is the real gate.
- **`config/sidebar.php` is dead config** — nothing reads it. Nav visibility lives in the hardcoded `items` array in `resources/react/components/Sidebar.tsx`. Editing the config file has no effect.
- Branch isolation is centralized in `app/Support/BranchScope.php`. Use its `applyTo*` helpers for any query over `users`, `transactions`, `loans`, or `expenses`; `branches.bypass` / `admin` / `developer` / `branch_id === null` are unrestricted.
- Audit writes go through `app/Services/AuditLogService.php` (spatie activitylog, 365-day retention). Use it for state-changing actions rather than ad-hoc logging.

## Commands

```bash
composer dev        # serve + queue:listen(default,emails) + pail + vite, via concurrently
composer test       # config:clear, then php -d memory_limit=512M artisan test
npm run dev         # vite only (expects php artisan serve on :8000)
npm run build       # production assets -> public/build
vendor/bin/pint     # formatter, laravel preset (no pint.json in repo)
```

- `composer dev`'s queue worker only listens on `--queue=default,emails`. **Backups use a separate `backups` queue** and are not covered by `composer dev`.
- Scheduler (`routes/console.php`) must be running: `php artisan schedule:run` every minute. `backup:ping` is the deciding agent — it reads per-tier cron expressions from `SystemSetting` rows and only runs what is due. See `docs/BACKUPS.md`.

## Testing

- **PHP: Pest.** `tests/Pest.php` binds `RefreshDatabase` **only to `Feature`** — Unit tests do not get a fresh DB.
- Test env is forced to sqlite `:memory:` in `phpunit.xml` regardless of `.env`, with `QUEUE_CONNECTION=sync` and `MAIL_MAILER=array`.
- **Feature tests do not auto-seed.** The established pattern is:
  ```php
  beforeEach(function () {
      $this->seed(SpatieRolePermissionSeeder::class);
      $this->seed(UserSeeder::class);
      $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
  });
  ```
  `DatabaseSeeder` runs `SpatieRolePermissionSeeder` **twice** (once to create roles, once after `UserSeeder` to backfill) — if you add seeded users, keep that two-pass ordering.
- Seeded logins are all `password`: `admin@`, `manager@`, `accountant@`, `loan@`, `secretary@` `example.com`, plus a `developer@example.com`.
- **E2E: Playwright**, `tests/e2e/*.spec.ts`, `npx playwright test`. `workers: 1` and `fullyParallel: false` are deliberate — the specs mutate a **real shared MySQL database** and will conflict if parallelized. 90s timeout, 1 retry.
- E2E prerequisites: `php artisan serve` on `127.0.0.1:8000`, assets built (`npm run build`) or Vite running, and a **seeded MySQL** database. `tests/e2e/helpers/auth.ts` reads one-time OTPs out of `storage/logs/laravel.log`, so logging must be capturing them.
- No ESLint, no `tsc`, no frontend test runner. Verification order: `vendor/bin/pint --test` → `php artisan test` → `npm run build`.

## Environment gotchas

- **The checked-in `.env` is a production config** (`APP_ENV=production`, `APP_URL=https://settlerscc.online`, debug off) and its MySQL credentials are rejected by the local server. Do not run `migrate`/`db:seed` against it casually — fix local credentials first, or use sqlite.
- `vendor/` here is installed **without dev dependencies**: `php artisan test` fails with `Command "test" is not defined`, and `vendor/bin/pint` and `vendor/bin/phpunit` do not exist. Run `composer install` (with dev) before trusting any test or lint result.
- `public/storage` is not linked — `php artisan storage:link` is required for uploaded images to resolve.
- Compiled views and `bootstrap/cache/events.php` are cached in this checkout; `bootstrap/cache/config.php` is not. Run `php artisan view:clear` when Blade changes appear to be ignored.
- Migrations are hand-named and **share duplicate timestamp prefixes** (two `2026_09_09_000001_*` files, two `2026_09_09_000002_*`). Ordering resolves by full filename; do not renumber existing files.
- `DB_DUMP_BINARY_PATH` must be set in `.env` for database-scope backups on Windows/Laragon.
- **A fresh worktree/clone needs `storage/framework/views/` to exist.** The `.gitignore` that keeps that directory alive is tracked, but the directory itself is not committed, so a `git worktree add` will not create it and every Blade render dies with `RuntimeException: View path not found.` If you ever delete that `.gitignore`, restore it. Same applies to any other empty-but-required storage dir.
- **Cache files are not the usual suspect.** `bootstrap/cache/*` and `storage/framework/views/*` are gitignored, so a new worktree starts clean. If a fresh worktree 500s on every page, the cause is almost always a bad `.env` (see the `config/backup.php` `BACKUP_NOTIFICATION_EMAIL` eager-validation trap) rather than a stale cache.
- **`env()`'s default argument only fires when a key is *missing*, not when it is present-but-empty.** Any `env('X', 'fallback')` therefore yields `''` if `.env` contains a bare `X=`. Use `?:` (`env('X') ?: 'fallback'`) when empty must also fall through — and never `||`, which returns a bool and will poison string configs.

## Docs & conventions

- **`FUNCTIONALIT_LIST.md` (repo root) is the verified system-functionality reference** — roles, per-role capabilities, and the exact permission gating in use. Read it before changing access rules.
- `docs/BACKUPS.md` covers the three backup tiers, B2 off-site setup, restore safety, and ops commands.
- `Leo/` is gitignored scratch space (only `Leo/audit_pipeline.ps1` is tracked) holding planning/audit markdown. Not authoritative — verify against code.
- `README.md` references a `setup.sh` that does not exist in this repo. Ignore it.
- Commit messages use Conventional Commits: `feat(auth):`, `fix(loans):`, `chore(gitignore):`, `test(audit):`.
- There is no `opencode.json`, CI workflow, or pre-commit hook. Nothing runs verification automatically.
