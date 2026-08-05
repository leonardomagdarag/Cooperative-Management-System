# Cooperative Management System - AGENTS.md

## Project Overview
Laravel 12 + React (Vite) SPA for "Settlers Credit Cooperative" management. Uses TailwindCSS v4, Spatie Laravel Permission, Laravel Sanctum, PHPMailer.

## Key Commands

| Task | Command |
|------|---------|
| Start all dev servers | `composer dev` (runs PHP server, queue, logs, Vite) |
| Run tests | `composer test` (clears config, runs Pest) |
| Run single test | `./vendor/bin/pest tests/Feature/ExampleTest.php` |
| Format code | `./vendor/bin/pint` |
| Build assets | `npm run build` |
| Vite dev server | `npm run dev` |
| Migrate + seed | `php artisan migrate --seed` |
| Clear all caches | `php artisan optimize:clear` |

## Architecture Notes

- **SPA entry**: `resources/react/main.tsx` → served via `routes/web.php` catch-all route
- **API routes**: `routes/api.php` (protected by `web` + `auth:web` middleware)
- **Auth**: Session-based (web) + Sanctum for API; custom OTP verification middleware (`otp.verified`)
- **Roles/Permissions**: Spatie Laravel Permission with custom `RolePermission` model
- **Queue/Jobs**: Database driver; emails sent via queued jobs
- **Database**: MySQL (configured in `.env`)

## Frontend Structure
```
resources/react/
├── main.tsx              # Entry point
├── App.tsx               # Routes + providers
├── pages/                # Page components (role-based overviews, services, members, etc.)
├── components/           # Shared UI components
├── contexts/             # React contexts (Auth, Theme, Sidebar, Toast)
└── api/                  # API client modules (axios-based)
```

## Backend Structure
```
app/
├── Http/Controllers/Api/     # API controllers (resource-style)
├   Http/Controllers/         # Web controllers (auth, dashboard, pages)
├── Models/                   # Eloquent models (30+)
├── Services/                 # Business logic (Notification, AuditLog, SecurityOtp, Turnstile)
├── Policies/                 # Authorization policies
├── Jobs/                     # Queued email jobs
├── Console/Commands/         # Artisan commands (cleanup, archive, notifications)
└── Traits/GeneratesCustomId.php  # Custom ID generation trait
```

## Testing
- Framework: Pest (extends `Tests\TestCase` with `RefreshDatabase`)
- Run all: `composer test`
- Run filtered: `./vendor/bin/pest --filter=TestName`
- Feature tests in `tests/Feature/`, unit in `tests/Unit/`

## Environment Setup
1. Copy `.env.example` → `.env`
2. Configure DB (MySQL), mail (log driver by default), Turnstile keys
3. `composer install && npm install`
4. `php artisan key:generate && php artisan migrate --seed`
5. `composer dev` to start all services

## Gotchas
- Vite proxy config (`vite.config.js`) forwards `/api`, `/sanctum`, auth routes to Laravel backend
- TailwindCSS v4 uses `@tailwindcss/vite` plugin (not PostCSS)
- React 19 + React Router 8 + TanStack Query v5
- Custom middleware: `public.registration.enabled`, `otp.verified`, `role:roleName`
- Session/Cache/Queue all use database driver (requires `php artisan queue:listen`)
- Run `php artisan storage:link` for file uploads (loan documents, announcements)