# Settlers Credit Cooperative — System Functionality

**Document:** `SYSTEM_FUNCTIONALITY(2026-10-12)`
**Date:** 2026-10-12
**System:** Cooperative Management System (Laravel 12 + React 19 SPA, Vite, TailwindCSS v4, Sanctum session auth, Spatie Permissions)
**Source of truth verified from:** `routes/web.php`, `routes/api.php`, `resources/react/App.tsx`, `PermissionCatalog.php`, `SpatieRolePermissionSeeder.php`, `ServiceAvailabilitySeeder.php`, `SystemSettingSeeder.php`, `SettingsPage.tsx`, `ServicesPage.tsx`, `HelpSupportTab.tsx`
**QA status:** This is the behavior and access reference for QA planning. A listed capability is not thereby confirmed by a current browser run; record observed results in [`docs/QA/QA_LOG.md`](docs/QA/QA_LOG.md).

---

## 1. User Types & Roles

| Role | `primary_role` | Officer? | Description |
|---|---|---|---|
| Guest (unauthenticated) | — | No | Public website visitor |
| Member | `member` | No | Regular cooperative member, self-service only |
| Administrator | `admin` | Yes | Full system access, all permissions |
| Chairman | `chairman` | Yes | Countersign approval, officer and branch management, ledger, logs, reports, expenses, collections, and tickets |
| Manager | `manager` | Yes | Broad operational oversight |
| Accountant (Treasurer) | `accountant` | Yes | Transactions, services, financial reporting |
| Loan Officer | `loan` | Yes | Loan processing and member loan support |
| Developer (testing) | `developer` | Yes | All catalog permissions except chairman-only countersign, granted through the normal permission matrix |
| Inactive / Terminated | `inactive`, `terminated` | No | Lifecycle states with no active permissions |

Access is enforced by:
- Auth (`auth:web`), email verification (`verified`), OTP verification (`otp.verified`), profile completion (`profile.completed`), service toggles (`service.availability:*`).
- Frontend `PermissionGate` in `App.tsx` (e.g. `members.view`, `loans.view`, `reports.view`, `services.view`, `transactions.view`, `announcements.view`, `officers.view`, `branches.view`, `logs.view`, `settings.update`, `officers.manage-permissions`, `oversight.approve`).
- Backend permission checks (`can(...)` / `abort_unless(...can(...))`).

Role dashboards (`/dashboard` with no section routes by role):
- `admin` → `AdminOverview`
- `chairman` and other unlisted officer roles → `OfficerOverview` only when the SPA user payload exposes `is_officer`; current payload alignment is a QA check (see `docs/QA/CODE_DOCUMENT_ALIGNMENT.md`).
- `manager` → `ManagerOverview`
- `accountant`/`treasurer` → `TreasurerOverview`
- `loan` → `LoanOverview`
- other officers → `OfficerOverview`
- members → `MemberOverview`

---

## 2. Guest (Public / Unauthenticated) Functionalities

Public layout routes (`PublicLayout` in `App.tsx`, `PageController` in `web.php`):

1. **View public website**
   - `GET /` — Home (`HomePage`)
   - `GET /about` — About (`AboutPage`)
   - `GET /news` — News (`NewsPage`)
   - `GET /stories` — Stories (`StoriesPage`)
   - `GET /contact` — Contact (`ContactPage`)
   - `GET /api/public-content` — session-aware public CMS content (hero, stats, services, FAQs, board, etc.)
   - `GET /api/announcements/public` — paginated public/cooperative announcements (title, 200-char excerpt, type, image, creator).
2. **Register as member (if enabled)**
   - `GET /register` gated by `public.registration.enabled` middleware.
   - `POST /register` (`LoginController@register`, `throttle:5,10`).
   - Controlled by Service Availability toggles: `service_registration_enabled` (global) + `service_registration_member_enabled`.
3. **Login (if enabled)**
   - `GET /login` gated by `public.login.enabled`.
   - `POST /login` and `POST /api/login` (`throttle:5,1`).
   - Controlled by toggles: `service_login_enabled` (global), `service_login_member_enabled`, `service_login_officer_enabled`.
4. **Password recovery**
   - `GET /forgot-password`, `POST /forgot-password` (send reset link, `throttle:5,10`).
   - `GET /reset-password`, `GET /reset-password/{token}`, and `POST /reset-password` (`throttle:5,1`); reset links expire after 30 minutes.
5. **Contact the cooperative**
   - `POST /contact` (web, `throttle:5,10`) and `POST /api/contact` (`throttle:5,1`) — name/email/message, dispatched via `SendEmailJob` + `ContactMessageMail` (queued).
6. **Redirect behavior**
   - Authenticated users visiting `/login`, `/forgot-password`, `/reset-password` are redirected to `/dashboard` (`GuestRoute`).
   - Unknown paths redirect to `/`.

---

## 3. Member Functionalities

Members have self-service permissions by default:
`dashboard.view, services.view, services.create, loans.create, deposits.view, deposits.create, withdrawals.view, withdrawals.create, announcements.view, profile.view, profile.update, notifications.view`

### 3.1 Authentication & Security
- Login / logout (`POST /api/logout`, `POST /logout`).
- Email verification gate (`VerifyEmailPage`, `POST /api/auth/resend-verification` with `throttle:3,10`).
- OTP challenge: `GET/POST /security/verify-otp`, `POST /security/verify-otp/resend` (`OtpChallengeController`). Successful verification establishes the `otp.verified` session state required by protected routes; the challenge itself is not behind that middleware.
- Profile completion gate (`ProfileCompletionPage`, `GET /api/profile/completion`, `profile.completed` middleware blocks loan/service actions until complete).
- Confirm password (`GET/POST /confirm-password`).
- Forgot / reset password flow (see Guest).

### 3.2 Dashboard / Overview
- `GET /api/dashboard` — accounts (savings, share-capital balances), active loan count.
- `GET /api/member-loans` — member's own loans (`MemberLoansPage` at `/dashboard/member-loans`).
- `MemberOverview` — balances, loans summary, pending requests, announcements preview.

### 3.3 Services (`/dashboard/services`, `ServicesPage`, permission `services.view`)
Member requires completed profile + per-service availability (`service.availability:deposit|withdrawal|loan_application|loan_payment`).

| Service | API | Notes / Wizards |
|---|---|---|
| Deposit Money | `POST /api/services/deposit` or Xendit checkout | `DepositWizard` — savings or share-capital, Counter (OTC), Online, or enabled Xendit hosted checkout; availability still governs Counter/Online service use. |
| Withdraw Money | `POST /api/services/withdrawal` | `WithdrawalWizard` — savings only, Counter/Online/Bank-transfer, may require OTP approval on large amounts |
| Apply for Loan | `POST /api/services/loan-application` | `LoanApplicationWizard` — personal/housing/business/emergency, availability `loan_application.{global,member,officer}` |
| Make Loan Payment | `POST /api/services/loan-payment` or Xendit checkout | `LoanPaymentWizard` — pay own active loan installments by Counter, Online, or enabled Xendit hosted checkout; availability `loan_payment.{global,member,officer,counter,online}`. |
| View own pending requests | `GET /api/history?status=pending&per_page=5` | Shown as “Pending Requests” card; “Awaiting Approval” badge, amount/type/date/note |

If a service is disabled, `ServiceMaintenanceModal` is shown.

### 3.4 Loans — Member View
- `POST /api/loans` (requires `profile.completed`) — create/encode own loan application.
- `GET /api/loans`, `GET /api/loans/{loan}` — view own loans.
- `GET /api/loans/loan-products`, `GET /api/loan-products` — browse available loan products.
- `POST /api/loans/{loan}/documents`, `GET /api/loans/{loan}/documents/{document}` — upload/download supporting documents.
- Track review status (`pending`, `under_review`, `endorsed_for_approval`), approval/rejection, disbursement, closure, payments.

### 3.5 Transaction History (`/dashboard/history`, `HistoryPage`)
- `GET /api/history` — member's own transaction history with filters (status, type, pagination).
- View deposits, withdrawals, loan payments, disbursements, fees; account type, amounts, dates, notes.

### 3.6 Help & Support (`Settings → Help & Support`, `HelpSupportTab`)
- **FAQs & Guides tab:** `GET /api/faqs` — searchable knowledge base, category pills, tags, expandable Q&A.
- **Tickets tab (own scope):**
  - `GET /api/tickets?scope=mine&status_group=active|closed|all` — list own tickets with counts.
  - `POST /api/tickets` — create ticket (`NewTicketModal`): subject, category, priority (low/medium/high/urgent).
  - `GET /api/tickets/{ticket}` — view thread (`TicketDetailModal`).
  - `POST /api/tickets/{ticket}/messages` — reply / message support staff.
  - Status badges: Open, In Progress, Resolved, Closed.
- Non-support-staff tickets route (`/dashboard/tickets`, `/dashboard/messages`) redirects into Settings → Help & Support → Tickets.

### 3.7 Announcements (`/dashboard/announcements`, permission `announcements.view`)
- `GET /api/announcements`, `GET /api/announcements/{announcement}` — view cooperative announcements.
- Public announcements also visible without login (see Guest).

### 3.8 Notifications (`/dashboard/notifications`, `NotificationsPage`)
- `GET /api/notifications` — paginated list.
- `GET /api/notifications/unread-count` — badge count.
- `POST /api/notifications/{notification}/read`, `POST /api/notifications/mark-all-read` — mark read.
- `DELETE /api/notifications/{notification}`, `DELETE /api/notifications/read/all` — delete.
- Types: account activity, loan updates, transaction approvals, service-unavailable notices, due reminders, ticket updates.

### 3.9 Profile (`/dashboard/profile`, `ProfilePage`)
- `GET /api/profile` — view personal data, address, contact, user/role.
- `PUT /api/profile` (multipart) — edit title, name, birth date/place, gender, education, civil status, religion, phone, Facebook, full address (country/region/province/city/barangay/street/zip).
- `POST /api/profile/email/send-otp` + `EmailChangeVerification` component — OTP-verified email change.
- `GET /api/profile/completion` — completion status.

### 3.10 Settings → My Account (`/dashboard/settings`, tab `account`, always visible)
- Change password with OTP: `POST /api/settings/password/send-otp` → `PUT /api/settings/password` (current + new + confirm + 6-digit code, strength meter, revokes trusted devices).
- Trusted devices: My Account lists browser/device label, last IP, last login, and trust status; device revocation and revoke-all require email OTP. A user account has one active login session, and a successful login on another browser/device invalidates the prior session after any required new-device OTP is verified.
- Deactivate account: `POST /api/settings/deactivate-account` (password confirm, sets `account_status=inactive`, logs out).
- Delete account: `DELETE /api/settings/account` (backend-supported).
- Privacy notice (Data Privacy Act).

### 3.11 Global Search
- `GET /api/search` — permission-scoped global search.

---

## 4. Officer — Common Functionalities (All Officer Roles)

All officers are `is_officer=true` with `dashboard.view, settings.view, notifications.view, profile.view, profile.update` at minimum.

- Role-based overview dashboard (Manager/Accountant/Loan/Officer).
- **Services in “officer mode”** (`ServicesPage`):
  - Member selector: `GET /api/members/search?q=`, `GET /api/members/{user}` — must select a member before Deposit/Withdraw/Loan/Payment cards unlock.
  - Act on behalf of member: Deposit, Withdraw, Loan Application, Loan Payment (same APIs as member + `selectedMember` context, gated by `officer` availability flag).
  - Balances shown are the *selected member’s* (savings, share-capital, active loans).
- **Members (read):** `GET /api/members`, `GET /api/members/search`, `GET /api/members/{user}` (`members.view`). The UI separately gates IDs, loans, activity, and financial tabs with `members.view-ids`, `members.view-loans`, `members.view-activity`, and `members.view-financial`.
- **Loans (read/review where permitted):** `GET /api/loans?loan_status=pending`, `GET /api/loans/{loan}`, loan documents download.
- **Announcements (read):** view all cooperative announcements.
- **Reports (read/export where permitted):** `/dashboard/reports` (`reports.view`).
- **Profile / Settings / Notifications / FAQs:** same as members.
- **Branch scoping:** developers are global. All built-in officer presets (`admin`, `chairman`, `manager`, `accountant`, and `loan`) grant `branches.bypass`; an active officer with that effective permission can access records across branches regardless of assigned branch. Without the permission, assigned officers stay scoped to their branch. A per-officer deny override can still remove the effective bypass. Members remain scoped to their assigned branch. Branch filters are available to unrestricted officers; the backend enforces branch scope for restricted officers.

---

## 5. Officer — Role-Specific Functionalities

### 5.1 Administrator (`admin`) — System administration
The admin preset is limited to system configuration, backups, security logs, troubleshooting tickets, officer account administration, and creating or updating announcements. It does not grant routine member, loan, service, transaction, branch management, or financial account workflows; those remain with operational officers and the manager. It grants `branches.bypass` for cross-branch scope where the admin has access to an operational workflow. Admin can also access explicitly admin-owned configuration such as roles, backups, public content, loan products, service availability, SMS/email, and payment gateway settings.
- **Monitoring:** dashboard and security logs (view/export).
- **Troubleshooting:** tickets and support configuration.
- **System administration:** officer account and permission management, settings, backups, and oversight requests.
- **Roles & Permissions** (`Settings → Roles & Permissions`, `/dashboard/role-permissions`, `role:admin` only):
  - `GET /api/roles` — list roles + permissions.
  - `POST /api/roles` — create custom role.
  - `PUT /api/roles/{role}/rename` — rename role.
  - `PUT /api/roles/{role}/permissions` — update permission set.
  - `GET /api/roles/{role}/restore-preset` — restore default preset.
  - `DELETE /api/roles/{role}` — delete role.
- **Officer permission overrides:**
  - `GET /api/officers/{user}/permissions`, `PUT /api/officers/{user}/permissions` (`officers.manage-permissions`).
- **Backups** (`Settings → Backup & Restore`):
  - `GET /api/backups`, `GET /api/backups/runs` — list tiers/runs.
  - `PUT /api/backups/settings` — schedules, scope (db/files/both), retention, encryption.
  - `POST /api/backups/offsite/test` — test the configured Backblaze B2 off-site connection.
  - `POST /api/backups/{tier}/run`, `POST /api/backups/{tier}/cleanup`, `GET /api/backups/{tier}/download`, `DELETE /api/backups/{tier}/backup`, `POST /api/backups/restore`.
  - Cron fallback: `GET /backup/ping?token=BACKUP_PING_TOKEN` → `backup:ping`.
- **System settings & toggles:**
  - `GET /api/settings/service-availability`, `PUT /api/settings/service-availability` (`settings.update`) — all services incl. registration/login (see §8).
  - `PUT /api/public-content` — edit public website CMS (`PublicContentPage`).
  - `POST/PUT/DELETE /api/loan-products*` — manage loan products (`LoanProductsPage`).
- **Admin-owned system actions:** officer management, logs, backups, role/permission configuration, service availability, and system integrations. Operational member, branch, transaction, loan, and financial account work belongs to the manager and other assigned officers.

### 5.1.1 Chairman (`chairman`)
Preset: oversight approval, officer and branch management, ledger and expense access, logs, reports, collections review/approval, and tickets. The chairman is the countersign approver for privileged actions and is subject to the same server-side branch, OTP, separation-of-duty, and workflow checks as every other user.

`oversight.approve` is chairman-only. Role and officer permission editors omit it, server authorization rejects it for non-chairman accounts (including the developer testing role), and pending permission changes cannot grant it. Ledger transaction changes remain in the chairman countersign workflow.

The `developer` role is a local testing role, not a production officer preset. It receives every catalog permission except `oversight.approve` and `transactions.approve-own`, so it exercises the same `can(...)` checks as any other role. Remove the account (and optionally the role preset) when testing is finished.

### 5.2 Manager (`manager`)
Preset: members (view/create/update/approve plus detail facets), services (view/create/update), loans (view/create/encode/update/approve/reject/disburse), transactions (view/view-all/create/update/approve/reversal/export), deposits & withdrawals (view/create/approve/reject), reports (view/export/financial), announcements (view), `branches.bypass`, logs (view), settings (view), tickets, notifications, profile, and collections review/approval. The default manager preset does not grant member deletion, announcement writes, officer management, or `settings.update`. Managers have cross-branch access through `branches.bypass`.
- Approve member applications (`POST /api/members/{user}/approve`, `POST /api/members/{user}/decline`).
- Approve transactions (`POST /api/services/approve/{transaction}`, withdrawal OTP `POST /api/services/transactions/{transaction}/send-otp`, `GET /api/services/pending`).
- Review/endorse/approve/reject/disburse/close loans (`POST /api/loans/{loan}/review|disburse|close`, `PUT /api/loans/{loan}`). Loan deletion is available only for untouched pending applications and always requires countersign by a different active chairman; reviewed or financially linked loans stay in the borrower's history.
- Manage permitted member, transaction, loan, ledger/expense, report, and collection workflows. Announcement writes, officer management, and settings changes require separately granted effective permissions.
- Tickets: view/edit/close/assign (the SPA direct ticket route recognizes admin/manager/accountant; the backend's support-staff predicate also recognizes a user with `tickets.manage`, so this UI/API difference must be audited rather than assumed consistent).

### 5.3 Accountant / Treasurer (`accountant`)
Preset: members (view/create/update/approve plus detail facets), services (view/create/update), loans (view), transactions (view/view-all/create/update/export — no `approve` in preset), deposits/withdrawals (view/create), announcements (CRUD), reports (view/export/financial), `branches.bypass`, settings (view), tickets, notifications, profile, and collection review. Accountants can access records across branches; branch CRUD still requires its separate `branches.create`, `branches.update`, or `branches.delete` permission.
- **Ledger** (`/dashboard/ledger`, `transactions.view`): `GET /api/ledger`, `POST /api/ledger`, `PUT /api/ledger/{transaction}`, `DELETE /api/ledger/{transaction}`, `GET /api/ledger/export`, `GET /api/ledger/summary`.
- **Expenses** (`/dashboard/expenses`): `GET/POST/PUT/DELETE /api/expenses`.
- **Reports:** financial, expenses, member activity/savings, loans, payments, balance sheet + download (`GET /api/reports/*`).
- Process member services where the underlying service and branch checks permit it, approve member onboarding, and view detail tabs granted by the member-detail facet permissions.
- Tickets support view (`isSupportStaff` includes accountant).

### 5.4 Loan Officer (`loan`)
The default loan role also grants `deposits.create`, allowing loan officers to record member deposits through Services without granting ledger or expense access.
Preset: dashboard.view, members.view, services.view, loans (view/create), `branches.bypass`, settings.view, tickets (view/reply), notifications.view, profile (view/update), collections (view-own/create/submit). The preset does not grant `loans.encode` or `loans.update`; loan encoding, review, edits, deletes, and officer document uploads are therefore unavailable unless those permissions are explicitly granted. The admin role can encode loans through its explicit admin policy exception. Loan officers can access records across branches, while loan lifecycle permissions remain separately enforced. **No `transactions.*`** — ledger/expenses return 403.
- **Loan Applications** (`Services → Loan Applications`, `LoanReviewModal`): loan officers can view the loan queue and product/member lookup; review and endorsement require `loans.update`, which is not in the default loan role preset.
- **Loans module** (`/dashboard/loans`, `loans.view`): `GET /api/loans/search-members`, `GET /api/loans/loan-products`, and `GET /api/loans/{loan}`. Encoding requires `loans.encode`; edits and officer uploads require `loans.update`. Loan deletion requests require `loans.update`, are limited to pending unreviewed loans with no linked records, and execute only after another active chairman countersigns. Borrowers can upload documents for their own loans.
- **Field Collections** (`/dashboard/collections`, `collections.*`): own-route envelope — `GET /api/collections/route-list` (excludes loans already recorded in an in-flight run for the same date, except items in your own draft which show as Collected), list/create own runs (`GET/POST /api/collections`), record/submit items — visibility and edits scoped to own runs; see `COLLECTIONS_REWORK.md`.
- Financial summary cards hidden in Services (`isLoanFocused`) — sees Active Loans + Pending Requests only.
- **No transaction visibility** — `transactions.view`/`transactions.view-all` intentionally not granted: `GET /api/ledger` and `GET /api/expenses` return 403 (enforced in `RoleSpecificAccessBehaviorAuditTest`).

### 5.5 Legacy role migration note
`secretary` is a legacy role name and is not an active supported role. Existing
`secretary` role data is migrated to `accountant` by the role migration and
seeder. Use the current `accountant` permissions in §5.3 when preparing QA;
do not treat old secretary-specific behavior as a separate role contract.

### 5.6 Officers Management (`/dashboard/officers`, `OfficersPage`, permission `officers.view`)
- List/search officers, view profile (`OfficerProfileModal`), create/edit via `OfficerFormModal`:
  - `GET /api/members` (officer filter) / member controller reused for officers.
  - `POST /api/members` — create officer/member.
  - `PUT /api/members/{user}`, `DELETE /api/members/{user}`, `POST /api/members/{user}/restore`, `DELETE /api/members/{user}/force`.
- Per-officer permission override (`OfficerPermissionModal` → `OfficerPermissionController`, `officers.manage-permissions`).
- Password reset OTP for a user: `POST /api/members/{user}/password/send-otp`.

---

## 6. Module-by-Module Functionality Reference

### 6.1 Authentication, Verification & Session Security
| Function | Endpoint / UI |
|---|---|
| Login / Logout | `POST /login`, `POST /api/login`, `POST /logout`, `POST /api/logout` |
| Public registration toggle | `public.registration.enabled` + `service_registration_*` |
| Public login toggle | `public.login.enabled` + `service_login_*` |
| Email verification | `GET /verify-email`, `GET /verify-email/{id}/{hash}`, `VerifyEmailPage`, resend API |
| OTP challenge (login-sensitive actions) | `GET/POST /security/verify-otp`, resend, `SecurityOtpService`; successful challenge completion is required by the `otp.verified` middleware on protected routes |
| Password reset | Broker generated email link, 30 minute expiry, reset routes + pages |
| Password change (OTP-gated) | Settings → My Account, `sendPasswordOtp` + `changePassword` |
| Email change (OTP-gated) | Profile → `sendEmailChangeOtp` + `EmailChangeVerification` |
| Withdrawal approval OTP | `POST /api/services/transactions/{transaction}/send-otp` (threshold `security_withdrawal_otp_threshold`, default ₱2,000) |
| Trusted devices / revoke | `GET /api/profile` device data; OTP-protected `POST /api/settings/trusted-devices/{device}/revoke*` and `/api/settings/revoke-devices*`; `TrustedDevice` (90-day default) |
| Deactivate / delete own account | `POST /api/settings/deactivate-account`, `DELETE /api/settings/account` |
| Turnstile / throttling | `Turnstile` service, `throttle:*` on auth/contact/OTP routes |

### 6.2 Members Management (`MembersPage`, `members.*`)
- List with pagination/search/filters: `GET /api/members`, `GET /api/members/search`.
- View full profile: `GET /api/members/{user}` + `MemberProfileModal` (personal, address, contact, accounts, loans). The UI separately gates ID, loan, activity, and financial views with the member-detail facet permissions in `PermissionCatalog`.
- Create (wizard): `POST /api/members` (`MemberWizardForm`, `members.create`).
- Update: `PUT /api/members/{user}` (`members.update`).
- Approve / Decline applications: `POST /api/members/{user}/approve|decline` (`members.approve`, `DeclineApplicationModal`).
- Special-loan eligibility toggle: `POST /api/members/{user}/toggle-special-loan`.
- Soft-delete / restore / force-delete: `DELETE`, `POST .../restore`, `DELETE .../force` (`members.delete`).
- Export: `POST /api/members/export` (`MemberExportModal`).
- Send password-change OTP for a member: `POST /api/members/{user}/password/send-otp`.

### 6.3 Services & Transactions (`ServicesPage`, `services.*`, `transactions.*`, `deposits.*`, `withdrawals.*`)
- Availability-gated wizards (Deposit/Withdrawal/Loan Application/Loan Payment) for self (member) or selected member (officer).
- Pending queue: `GET /api/services/pending` + `TransactionApproval` modal; approve: `POST /api/services/approve/{transaction}` (`transactions.approve`/`deposits.approve`/`withdrawals.approve`).
- An officer cannot approve or reject a transaction they recorded unless they also have the explicit `transactions.approve-own` permission. This permission only lifts the self-approval restriction; the existing permission for the transaction type is still required. It is available through per-officer permission overrides and is not included in built-in role presets.
- **Posting and approval workflow:** An officer who records a member's cash deposit, counter payout, or counter loan payment at the branch posts it immediately. A member's self-service counter request stays pending and requires a manager or chairman approval. Online and bank-transfer transactions stay pending; Xendit provider verification confirms payment but does not credit/debit an account or complete a loan payment by itself. A manager can approve only transactions in their assigned branch when `branches.bypass` is off. When the approver has branch bypass, only a chairman can approve. The transaction type permission and branch access checks still apply; high-value withdrawals also require OTP.
- Account types: `savings`, `share-capital`; transaction types: `deposit`, `withdrawal`, `loan_payment`, `loan_disbursement`, `interest`, `fee`.
- Channels/modes: Counter (OTC), Online, Bank Transfer (withdrawal).

### 6.4 Loans & Loan Products (`LoansPage`, `MemberLoansPage`, `LoanProductsPage`, `loans.*`)
- Loan products: `GET/POST /api/loan-products`, `PUT/DELETE /api/loan-products/{loanProduct}`, `POST .../toggle` (enable/disable) — managed in Settings → Loan Products (`settings.update`).
- Loans: list/filter (`loan_status`, pagination), search members for encoding, view detail, create/encode, update, delete, review (`under_review`/`endorsed_for_approval`/approve/reject), disburse, close, document upload/download.
- Member loan tracker: `MemberLoansPage` + `GET /api/member-loans`.
- Guards: `profile.completed` required to apply/encode; `service.availability:loan_*`; loan-focused role hides financial cards.

### 6.5 Ledger (`LedgerPage`, `transactions.*`)
- `GET /api/ledger` (filterable ledger), `GET /api/ledger/summary`, `GET /api/ledger/export` (CSV/Excel), `POST/PUT/DELETE /api/ledger*` (`transactions.view|create|update|void|export`, `transactions.view-all` for all branches).
- **Edits are countersigned.** `PUT /api/ledger/{transaction}` does not mutate directly; it records a `transaction.update` privileged-action request and returns 202. The change is applied only after an active chairman holding `oversight.approve` approves it through `/api/oversight/requests*` (`CountersignService::performAction`). The requester cannot approve their own edit. Only `pending` transactions can be edited or deleted, so the SPA shows Edit/Delete only on pending rows and system-generated `interest`/`fee` entries are never editable.

### 6.6 Expenses (`ExpensesPage`)
- `GET/POST /api/expenses`, `PUT/DELETE /api/expenses/{expense}` — record operating expenses, categorize, filter, view in reports.
- **Changes and deletions are countersigned.** Both `PUT /api/expenses/{expense}` and `DELETE /api/expenses/{expense}` do not mutate directly; they record `expense.update` or `expense.delete` privileged-action requests and return 202. The change or deletion is applied only after an active chairman holding `oversight.approve` approves it through `/api/oversight/requests*` (`CountersignService::performAction`). Self-approval is forbidden. While a request is pending, duplicate updates/deletions on the same expense return 409 Conflict, and the Expenses SPA displays an "Awaiting approval" badge on the expense row and disables further edit/delete actions until approved or rejected.

### 6.7 Reports (`ReportsPage`, `reports.*`)
- Types (`GET /api/reports/*`): `financial`, `expenses`, `member-activity`, `member-savings`, `loans`, `payments`, `balance-sheet` (+ `download`), `interest-disbursements` (+ `{id}` drill-down), `options` (filter metadata).
- Finance workspace: persistent grouped sidebar on desktop and grouped report selector on mobile. Overview contains Financial Summary and Balance Sheet (management report); Savings & Interest contains Member Savings and Interest Credits; Loans & Collections contains Loan Register and Loan Payment Collections; Transactions & Expenses contains Transaction History and Operating Expenses. Member/officer registers are no longer listed here; their existing APIs remain available.
- Interest Credits (`interest.view`): paginated batches with member-level drill-down. Preview and export share date/account filters; counts, skipped counts and credited amounts are restricted to the effective branch, including shared batches. Export type remains `interest_disbursements`.
- Controls: period presets (day/week/month/year/custom), date inputs, branch/member/loan/category/activity/account filters, sorting for transaction history, pagination, loading/error/retry states, and download progress. Filters reset pagination. Charts appear only in Financial Summary; its trailing six-month breakdown is separate from the selected period totals.
- Downloads: `POST /api/reports/export` accepts `format: excel|pdf`; Excel is a genuine `.xlsx` workbook with numeric currency/date cells, safe text identifiers, frozen headers, filters and totals. PDF includes report/filter metadata, repeating headers and page numbers. Both include all matching records, using the same rows as previews. Financial Summary uses `report_type: financial_report`.
- Export authorization requires both effective `reports.view` and `reports.export`, plus `reports.financial` for Balance Sheet or `interest.view` for Interest Credits. The standalone balance-sheet download uses the same checks. Member/officer API exports also require their respective view permission. Branch isolation applies to previews and downloads.
- Date semantics are explicit: transactions use transaction dates (creation date fallback), expenses use expense dates, loans use application dates (creation date fallback), and interest uses batch creation dates. Savings/loan balances are current. The simplified balance sheet combines cash through the end date with current account/loan balances and residual retained earnings; it is labeled as a management report, not an audited or fully historical statement. Existing posting and financial calculation rules are unchanged; reversal rows are displayed and exported as negative amounts.

### 6.8 Announcements (`AnnouncementsPage`, `announcements.*`)
- Authenticated: `GET/POST /api/announcements`, `GET/PUT/DELETE /api/announcements/{announcement}` (visibility: public/cooperative/everyone, type, image via `storage` link, creator).
- Public: `GET /api/announcements/public` + News page; officers manage via `announcements.create|update|delete`.

### 6.9 Branches / Offices (`BranchesPage`, `branches.*`)
- `GET /api/branches/options` (dropdowns), `GET /api/branches`, `POST /api/branches`, `PUT/DELETE /api/branches/{branch}` (`branches.view|create|update|delete`).

### 6.10 Tickets & Support Messaging (`TicketsPage`, Help & Support, `tickets`)
- Member: create/view/reply own tickets (see §3.6).
- Support staff (admin/manager/accountant): full queue at `/dashboard/tickets` — `GET /api/tickets` (all scopes), `GET /api/tickets/support-officers`, `GET /api/tickets/{ticket}`, `POST .../messages` (reply), `PATCH .../status` (open/in_progress/resolved/closed), `PATCH .../assign` (assign officer).
- Legacy `/dashboard/messages` redirects to tickets for support staff, to Help & Support for others.

### 6.11 FAQs / Knowledge Base
- `GET /api/faqs` — categorized, tagged, searchable FAQs consumed in Help & Support → FAQs & Guides.

### 6.12 Notifications (see §3.8)
- Full lifecycle: list, unread count, mark read/all-read, delete single/all-read; queued email + in-app via `NotificationService`, `SendEmailJob` (PHPMailer, DB queue, `queue:listen` required).

### 6.13 History
- `GET /api/history` — self transaction history (member) / permission-scoped history; consumed by `HistoryPage` and Services pending-requests card.

### 6.14 Audit Logs (`LogsPage`, `logs.*`)
- `GET /api/logs` (`logs.view`) — filterable audit trail via `AuditLogService` (auth, member, loan, transaction, settings, security events).
- `logs.export` exists in the permission catalog, but the current route list and `LogsPage` expose no log-export action. Treat this as an implementation/catalog mismatch to inspect, not as an implemented export feature.

### 6.15 Global Search
- `GET /api/search` — permission-scoped cross-entity search (members, loans, transactions, announcements, tickets per grants).

### 6.16 Profile & Settings (see §3.9–3.10 + §7–8)
- Profile view/edit/completion + email OTP; Settings tabs (see §7).

### 6.17 Public Content Management (`PublicContentPage`, `settings.update`)
- `GET /api/public-content` (public read) / `PUT /api/public-content` (admin edit) — sections from `SiteContentCatalog`/`SiteContentSeeder`: home/hero, stats, services, about, news, stories, board members, contact info, footer, legal/privacy.

### 6.18 Backup & Restore (`BackupPage`, `backups.*`, admin)
- Tiers: hourly and daily local archives plus an off-site Backblaze B2 replica; fixed schedules, retention, and optional archive encryption (`BACKUP_ARCHIVE_PASSWORD`). The UI can view runs, manage settings, test off-site connectivity, run/clean/download/delete archives, and request restore through the countersigned workflow. `backup:ping` + `/backup/ping` remain the scheduler fallback. See `docs/BACKUPS.md`.

### 6.19 Xendit Payment Gateway (`XenditPage`, Deposit/Loan Payment wizards)
- **Settings:** administrators manage test/live mode, encrypted secret API keys, callback tokens, and connection tests through `GET/PUT /api/settings/xendit`, `PUT/DELETE /api/settings/xendit/credentials`, and `POST /api/settings/xendit/test`.
- **Member/officer payments:** Deposit and Loan Payment wizards fetch `GET /api/services/xendit/status`, create hosted Payment Sessions through the deposit or loan-payment checkout route (each carries profile-completion and matching service-availability gates), and allow a user-initiated authoritative status check through `GET /api/services/xendit/verify/{transactionNumber}`.
- **Webhook:** `POST /api/webhooks/xendit` is a public callback-token-verified endpoint. Completion requires the Xendit session and underlying payment to match the stored transaction reference, PHP amount, and successful payment status. External checkout verification requires test credentials; never use live transactions for manual QA.

### 6.20 Supporting Security, Address, and Infrastructure Behaviors
- **PSGC address lookup:** `GET /psgc/regions|provinces|cities|barangays` (and individual-code endpoints) supplies address selectors used by registration/profile/member workflows.
- **Confirm password:** authenticated `GET/POST /confirm-password` validates `current_password`, records the confirmation timestamp, and redirects to the intended path. The current GET response is a simple server response rather than a dedicated React page.
- **Turnstile and throttling:** Login, registration, and password recovery render the Turnstile widget when configured and submit its response; applicable auth/contact/OTP routes also carry Laravel throttles. Availability and configuration must be recorded during audit.
- **Staff member password OTP:** authorized staff can request a password-change OTP for a member through `POST /api/members/{user}/password/send-otp` (`members.update`).
- **Withdrawal approval OTP:** authorized staff can request an approval OTP through `POST /api/services/transactions/{transaction}/send-otp`; threshold behavior is configuration-dependent.
- **Backup scheduler fallback:** `GET /backup/ping?token=...` is an infrastructure endpoint protected by `BACKUP_PING_TOKEN`, not a normal browser workflow. It invokes `backup:ping` for due schedules.

---

## 7. Settings Tabs Matrix (`SettingsPage`)

| Tab | Key | Visible To | Function |
|---|---|---|---|
| My Account | `account` | Everyone | Edit profile, change password (OTP), trusted devices, deactivate/delete, privacy notice |
| Help & Support | `help-support` | Everyone | FAQs & Guides + Tickets & Messages (see §3.6) |
| Public Page | `public-content` | Admin | Edit public website CMS |
| Loan Products | `loan-products` | Non-members with `settings.view` or `loans.view` | CRUD + enable/disable loan products |
| Roles & Permissions | `role-permissions` | Non-members, admin or `officers.manage-permissions` | Manage roles + permission sets + restore presets |
| Service Availability | `service-availability` | Admin | Toggle services/channels/audiences through a countersigned request (see §8) |
| Financial Accounts | `financial-accounts` | Non-members with `financial_settings.update` (admin, manager) | Interest rates/intervals/min balance, withdrawal limits and account toggles, manual interest run; all countersigned (see §8.1) |
| SMS Service | `sms` | Admin | Configure and test SMS settings |
| Email Service | `email` | Non-members with `settings.update` | Configure and test email settings |
| Payment Gateway | `xendit` | Admin | Configure Xendit test/live credentials, connection test, and webhook guidance |
| Backup & Restore | `backups` | Non-members, admin or `backups.view`/`backups.manage` | Schedules, scopes, retention, cloud creds, run/restore/download |

The Settings tab accepts admins and users with `officers.manage-permissions` for Roles & Permissions, but direct navigation to `/dashboard/role-permissions` is currently admin-gated by `App.tsx` and the API controller is also admin-only. Audit this discrepancy as observed behavior.

---

## 8. Service Availability Toggles (`ServiceAvailabilityPage`, `SettingsController`)

`GET/PUT /api/settings/service-availability` both require `settings.update`. The current Settings UI exposes the tab to admins; updates create a countersign request and may set `notify_affected`.

| Service | Flags |
|---|---|
| Member Registration | `global`, `member` |
| Login | `global`, `member`, `officer` |
| Deposits | `global`, `member`, `officer`, `counter` (OTC), `online` |
| Loan Applications | `global`, `member`, `officer` |
| Loan Payments | `global`, `member`, `officer`, `counter`, `online` |

The **Withdrawals** card was moved to §8.1; its `service_withdrawal_*` keys and `service.availability:withdrawal` middleware behaviour are unchanged, but `GET/PUT /api/settings/service-availability` no longer exposes or accepts `withdrawal`.

Disabled services block the corresponding wizard/API (`service.availability` middleware) and show `ServiceMaintenanceModal`. Optionally notifies all/members/officers via in-app notification.

### 8.1 Financial Accounts (`FinancialAccountsPage`, `SettingsController`, `InterestService`)

High-security settings available to admins and managers and applied only after **chairman countersign** approval. Endpoints: `GET/PUT /api/settings/financial`, `POST /api/settings/financial/interest/run` (gated by `financial_settings.view` / `financial_settings.update` / `interest.run`).

| Setting | Keys |
|---|---|
| Savings interest | `financial_savings_interest_rate`, `financial_savings_interest_interval`, `financial_savings_min_balance_to_earn` |
| Share-capital interest | `financial_share_capital_interest_rate`, `financial_share_capital_interest_interval`, `financial_share_capital_min_balance_to_earn` |
| Interest run options | `financial_interest_min_payout`, `financial_interest_notify_members` |
| Withdrawal rules | `financial_withdrawal_min_amount`, `financial_withdrawal_max_amount`, `financial_withdrawal_savings_enabled`, `financial_withdrawal_share_capital_enabled` (stored, inert while share capital is non-withdrawable) |
| Withdrawal channels (relocated) | `service_withdrawal_enabled`, `service_withdrawal_member_enabled`, `service_withdrawal_officer_enabled`, `service_withdrawal_counter_enabled`, `service_withdrawal_online_enabled`, `service_withdrawal_bank_transfer_enabled` |
| Idempotency markers | `financial_savings_last_credited_at`, `financial_share_capital_last_credited_at` |

- Interest is a nominal **annual** rate prorated by the interval (`daily = /365`, `monthly = /12`, `yearly = /1`). Only active members (Spatie role `member`) with an account balance at or above the minimum-to-earn are eligible.
- `interest:accrue` is scheduled every minute and dispatches `DisburseInterestJob` (queue `default`) only when the interval is due. The countersigned **Run now** action dispatches the same job for a dated manual batch.
- Each batch creates one `interest_disbursements` header plus `interest_disbursement_items` lines and a completed **`interest`** transaction per member. Per-member amounts below `financial_interest_min_payout` are recorded as skipped. `batch_key` + `financial_{type}_last_credited_at` make a run idempotent per period. Withdrawals to savings return **422** when `financial_withdrawal_savings_enabled` is off, and enforce the configured min/max.

Other system settings (seeded, `SystemSettingSeeder`): site name/tagline, maintenance mode, loan min/max/interest/OTP/due reminders, notifications (email/SMS/retention), security (failed logins, session timeout, password strength/min-length, OTP expiry/attempts, trusted-device days, withdrawal OTP threshold), payments (online enabled, GCash/PayMaya/bank), business hours/closed days, backup encryption/schedules/scopes/retention.

---

## 9. Maintenance & Downtime Page

`resources/views/maintenance.blade.php` (standalone Blade, no Vite/DB/session) at `GET /maintenance`, plus `resources/views/errors/503.blade.php`, which renders the same view whenever Laravel maintenance mode is active.

**When it appears**

| Trigger | Reaches | Copy |
|---|---|---|
| Login `global` off | `GET /login`, `POST /api/login` | "sign-in is temporarily unavailable", everyone affected |
| Login `member` off | `POST /api/login` (members only; officers still sign in) | Names the audience, states officers are unaffected |
| Login `officer` off | `POST /api/login` (officers only; members still sign in) | Names the audience, states members are unaffected |
| Restore in progress | **Every** route (Laravel maintenance mode) | "We are restoring your data" |
| Plain `artisan down` | Every route | Generic "scheduled maintenance" |

**Behaviour**

- Auto-refreshes (`<meta http-equiv="refresh">`, 15s default, 5–30s) and offers a **Try again** link. No separate admin bypass was added — a restore keeps the existing random `--secret` bypass.
- `POST /api/login` answers **403**, the `GET /login` guard answers **503**, both tagged `maintenance: true` + `redirect: /maintenance`; `resources/react/api/client.ts` follows the redirect. (These statuses are deliberate — 423 is reserved for OTP/service gating.)
- Sign-in switches are enforced in three places that must stay in agreement: `EnsurePublicLoginEnabled` (`GET /login`), `Api\AuthController::login` (the API, which historically ignored the *global* switch entirely), and the audience flags that select the copy.
- State lives in `storage/app/maintenance-state.json`, not cache or the DB, because a restore replaces both. `MaintenanceState::syncLoginAvailability()` re-derives the reason from `SystemSetting` on read, so a switch flipped outside the UI (or re-enabled by an admin) still reads true. Entries older than the staleness window are treated as debris and fall back to generic copy.

---

## 10. Permission Catalog (Summary)

`PermissionCatalog::groups()` is the canonical list. It includes dashboard; member detail facets (`members.view-loans`, `members.view-ids`, `members.view-activity`, `members.view-financial`); services; loan lifecycle; transaction approval, including the separate self-approval grant (`transactions.approve-own`); transaction reversal; deposits; withdrawals; announcements; reports; officers; branches; logs; settings; financial accounts (`financial_settings.view`, `financial_settings.update`, `interest.view`, `interest.run`); backups; tickets; notifications; profile; oversight; collections; and receipt reissue permissions. `logs.export` remains a declared permission without a current log-export route or UI.

Current officer presets are `admin`, `chairman`, `manager`, `accountant`, and `loan`; the effective permission returned to the SPA also includes permitted officer overrides. Member, inactive, and terminated behavior is established by the user/role seeders and authorization code. Use `PermissionCatalog::rolePresets()` and `$user->can(...)` as the authority instead of this summary when testing access.

---

## 11. Cross-Cutting / Technical Notes

- SPA entry `resources/react/main.tsx` → `routes/web.php` catch-all serves `react` view; Vite proxies `/api`, `/sanctum`, auth routes to Laravel. The catch-all **must stay last** in `routes/web.php`; `/maintenance` is declared before it and is itself in the Vite proxy list.
- Session + Sanctum auth; DB drivers for session/cache/queue (`php artisan queue:listen` required for emails/notifications).
- Queued jobs: `SendEmailJob` (contact, OTP, notifications, reminders); artisan commands: cleanup/archive/notifications/loan reminders.
- File uploads: `php artisan storage:link` (loan docs, announcement images, profile).
- Commands: `composer dev` (PHP+queue+logs+Vite), `composer test` (Pest), `./vendor/bin/pint`, `npm run build|dev`, `php artisan migrate --seed`, `php artisan optimize:clear`.

---

*End of SYSTEM_FUNCTIONALITY(2026-10-12). Reconciled against the active route, SPA, middleware, and permission sources on 2026-10-12; browser verification is tracked separately in `docs/QA/QA_LOG.md`.*
