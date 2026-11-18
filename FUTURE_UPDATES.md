# Future Updates & Planned Enhancements

This document tracks planned system enhancements for **Settlers Credit Cooperative** that are not yet implemented in the current release.

---

## 1. Automated Composer & npm Dependency Vulnerability Alerts (Admin)

Notify administrators automatically when any installed PHP (`composer.lock`) or frontend (`package-lock.json`) package has a known security vulnerability.

### Proposed Implementation
- **Custom Artisan Command** (e.g., `security:audit-dependencies` in `app/Console/Commands/`):
  - Parses `composer.lock` (`packages` + `packages-dev`) and `package-lock.json` (`packages`, `lockfileVersion: 3`) directly in PHP.
  - **Shared-hosting-safe API checks** (does not depend on `composer`, `node`, `npm`, or `proc_open` being available on Namecheap):
    - **npm:** `POST https://registry.npmjs.org/-/npm/v1/security/advisories/bulk` (native npm bulk advisory endpoint, sending `{ "package": ["version"] }`).
    - **Composer:** Packagist Security Advisories API (`https://packagist.org/api/security-advisories/`) or **OSV.dev** (`POST https://api.osv.dev/v1/querybatch`, which supports both `Packagist` and `npm` ecosystems in one schema).
  - **Runtime vs. Dev classification:** Flag whether a vulnerable package is a runtime dependency (`require` / `dependencies` bundled into `public/build`) or a dev/build-only dependency (`require-dev` / `devDependencies`), along with severity and patched version info.
  - **Deduplication & Noise Control:** Track previously alerted advisory IDs (e.g., via `SystemSetting` or a dedicated tracking table) so admins are notified once per newly discovered advisory (or when severity escalates) rather than on every scheduled run.
  - **Graceful Network Handling:** Use short HTTP timeouts and `try/catch` so external registry downtime logs a warning without breaking the scheduler.
- **Scheduler & Notifications:**
  - Schedule `->daily()` (or `->weekly()`) with `->withoutOverlapping()` in `routes/console.php`.
  - Notify active `admin` users via queued email (`SendEmailJob` on the `emails` queue, following `BackupNotificationService::administratorEmails()`) and in-app `Notification` records (`NotificationService`), and log scan results via `AuditLogService`.
  - Optional: surface latest scan status and a manual "Run Check Now" button in the Admin Security/Settings UI behind a dedicated permission in `app/Support/PermissionCatalog.php`.

---

## 2. Reintroduce the Loan Officer (`loan`) Role

Restore the dedicated Loan Officer role that was previously retired in `database/migrations/2026_11_06_000001_remove_legacy_loan_role_permissions.php`.

### Proposed Scope
- **Authorization & Catalog (`app/Support/PermissionCatalog.php`):**
  - Re-add `loan` (or `loan_officer` — keep naming consistent with existing conventions and `User::primaryRole`) to `officerRoles()`, `roleDisplayNames()`, `roleDescriptions()`, and `rolePresets()`.
  - Define a least-privilege preset aligned with the cooperative's segregation-of-duties model:
    - Can view members, loans, and services; create/encode loan applications; perform loan CI/auditing; and create/submit field collection runs.
    - **Cannot** self-approve loans (`loans.approve` / `loans.reject`), disburse funds (`loans.disburse`), approve transactions (`transactions.approve` / `transactions.approve-own`), or modify system/role settings.
- **Seeders, Branch Scope & UI:**
  - Update `SpatieRolePermissionSeeder` and `UserSeeder` (while reconciling Namecheap vs. local officer email fixtures carefully).
  - Ensure `BranchScope`, `CheckServiceAvailability`, and frontend role labels/badges handle the restored role cleanly.
  - Add a migration to restore/seed the Spatie role and its permissions cleanly without breaking existing `unassigned_officer` or custom-role records.

---

## 3. Reintroduce Collection Runs (Improved Implementation)

Re-implement the field **Collection Runs** workflow for loan officers (see historical design notes in `docs/system details/reworks/system-reworks/COLLECTIONS_REWORK.md`) with cleaner architecture, stronger invariants, and better UX.

### Key Requirements & Improvements
- **Separate Domain Tables (Never Container Rows in `transactions`):**
  - Maintain dedicated `collection_runs`, `collection_run_items`, and `collection_run_approvals` tables.
  - Real `Transaction` rows are created **only** after the run completes its approval chain—preserving the invariant that member/loan balances never move at draft or submission time.
- **Configurable Multi-Stage Approval Chain:**
  - Snapshot the approval stages at submission time (e.g., Accountant review → Manager approval) with full per-stage audit records (`collection_run_approvals`).
  - Enforce maker-checker separation: the submitting officer cannot approve any stage of their own run.
- **Improved Operational Edge-Case Handling:**
  - Explicit handling for partial item failures (e.g., loan settled before run approval) without corrupting the whole run (`partially_applied` + clear item-level failure reasons and reconciliation tools).
  - Late-submission flagging, branch scoping (`BranchScope`), orphaned-run reassignment if an officer is deactivated mid-route, and idempotent approval/application locking to prevent double-posting.
  - Clean receipt issuance once items become real approved transactions.
- **Improved UI/UX:**
  - Fast mobile-friendly route entry for loan officers (borrower lookup, visited/unvisited status, cash collected, notes).
  - Clear review/reconciliation screen for Accountants and Managers comparing expected route totals vs. cash remitted.

---

## 4. Loan Request Auditing (CI, Interview & Background Check)

Add a structured **Credit Investigation (CI), Applicant Interview, and Background Check** auditing workflow for Loan Officers prior to loan approval.

### Proposed Scope
- **Structured CI / Audit Record per Loan Application:**
  - Dedicated table/model (e.g., `loan_audits` or `loan_investigations` linked to `loans`) capturing:
    - Investigation type/checklist: residence/address verification, employment/income or livelihood check, character & co-maker/guarantor verification, existing obligation/capacity review, and applicant interview summary.
    - Outcome/recommendation (e.g., `recommended`, `recommended_with_conditions`, `not_recommended`, `needs_follow_up`) with required structured findings/remarks.
    - Visit/interview date, conducting Loan Officer (`auditor_id`), and submission timestamp.
- **Private Supporting Evidence:**
  - Allow attaching CI photos/documents (e.g., residence/business verification photos, signed interview sheet) stored in private storage with permission-gated viewing (never public URLs), following the pattern used for loan documents.
- **Workflow & Segregation of Duties:**
  - Introduce dedicated permissions in `PermissionCatalog.php` (e.g., `loans.audit` / `loans.ci`).
  - Gate or surface CI status clearly in the loan approval queue so Accountants, Managers, and the Chairman can review the Loan Officer's CI findings before executing `loans.approve` or `loans.reject` (configurable whether a completed CI is mandatory above a loan amount threshold or for specific loan types).
  - Log every CI submission and update through `AuditLogService`, and keep CI audit records immutable once the loan reaches a final decision.
