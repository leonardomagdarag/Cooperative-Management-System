# Settlers Credit Cooperative Management System

A cooperative management system for Settlers Credit Cooperative. It provides
public information pages and authenticated tools for members and cooperative
officers to manage accounts, services, loans, collections, financial records,
support, and administration.

## System overview

- **Application:** Laravel 12 monolith with PHP 8.4.
- **User interface:** React 19 single page application, built with Vite and
  Tailwind CSS. Laravel serves one React shell; the standalone maintenance page
  remains a Blade view so it can render when the database is unavailable.
- **Authentication:** Session and cookie authentication through Sanctum, with
  email verification, OTP verification, profile completion, and permission
  checks on protected workflows.
- **Authorization:** Spatie roles and permissions, with the permission catalog
  maintained in `app/Support/PermissionCatalog.php` and server-side checks as
  the enforcement boundary.
- **Data and workflows:** Member and officer operations, branch-scoped records,
  deposits and withdrawals, loan origination and payment, field collections,
  ledger and expenses, reports, tickets, notifications, audit logs, and
  administrative settings. Privileged changes use the countersign workflow.
- **Browser QA:** Playwright end-to-end specs in `tests/e2e/`, configured to run
  sequentially against a local Laravel server and seeded MySQL database.

The verified behavior and role-level access reference is
[`FUNCTIONALIT_LIST.md`](FUNCTIONALIT_LIST.md). Preparation and execution notes
for manual QA are in [`docs/QA/README.md`](docs/QA/README.md), with run and
finding tracking in [`docs/QA/QA_LOG.md`](docs/QA/QA_LOG.md). The current
code/document baseline and automated-QA handoff are
[`docs/QA/CODE_DOCUMENT_ALIGNMENT.md`](docs/QA/CODE_DOCUMENT_ALIGNMENT.md) and
[`docs/QA/AUTOMATED_QA_PROMPT.md`](docs/QA/AUTOMATED_QA_PROMPT.md).

## Local development

This repository's expected local environment is documented in `AGENTS.md`.
In brief, configure `.env`, install Composer and npm dependencies, prepare the
database, then run `composer dev`. For browser QA, use the explicit Playwright
prerequisites and safety notes in `docs/QA/README.md`.

Useful commands:

```bash
composer dev
npm run build
php artisan test
npx playwright test
```

Playwright tests mutate shared seeded data. Keep the configured single worker
and run them only against the intended QA database. Do not run the end-to-end
suite against production data.
