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
| Chairman | `chairman` | Yes | Operational oversight and countersign approval authority |
| Manager | `manager` | Yes | Broad operational oversight |
| Accountant (Treasurer) | `accountant` | Yes | Transactions, services, financial reporting |
| Loan Officer | `loan` | Yes | Loan processing and member loan support |
| Developer / Inactive / Terminated | `developer`, `inactive`, `terminated` | No | No active permissions / lifecycle states |

Access is enforced by:
- Auth (`auth:web`), email verification (`verified`), OTP verification (`otp.verified`), profile completion (`profile.completed`), service toggles (`service.availability:*`).
- Frontend `PermissionGate` in `App.tsx` (e.g. `members.view`, `loans.view`, `reports.view`, `services.view`, `transactions.view`, `announcements.view`, `officers.view`, `branches.view`, `logs.view`, `settings.update`, `role: admin`).
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
   - `POST /login` (`throttle:10,1`) and `POST /api/login` (`throttle:10,1`).
   - Controlled by toggles: `service_login_enabled` (global), `service_login_member_enabled`, `service_login_officer_enabled`.
4. **Password recovery**
   - `GET /forgot-password`, `POST /forgot-password` (send reset link, `throttle:5,10`).
   - `GET /reset-password`, `GET /reset-password/{token}`, `POST /verify-token`, `POST /reset-password` (`throttle:5,1`).
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
| Deposit Money | `POST /api/services/deposit` or PayMongo checkout | `DepositWizard` — savings or share-capital, Counter (OTC), Online, or enabled PayMongo hosted checkout; availability still governs Counter/Online service use. |
| Withdraw Money | `POST /api/services/withdrawal` | `WithdrawalWizard` — savings only, Counter/Online/Bank-transfer, may require OTP approval on large amounts |
| Apply for Loan | `POST /api/services/loan-application` | `LoanApplicationWizard` — personal/housing/business/emergency, availability `loan_application.{global,member,officer}` |
| Make Loan Payment | `POST /api/services/loan-payment` or PayMongo checkout | `LoanPaymentWizard` — pay own active loan installments by Counter, Online, or enabled PayMongo hosted checkout; availability `loan_payment.{global,member,officer,counter,online}`. |
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
- Trusted devices: `POST /api/settings/revoke-devices` — invalidate all sessions/devices.
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
- **Branch scoping:** developers are global; admin/chairman/manager users retain cross-branch access through `branches.bypass`; officers assigned to the Main branch can access all branches; officers assigned to another branch are restricted to that branch even if a stale role grant or officer override contains bypass. Members are scoped to their assigned branch. Main is the default for new member records and seeded officers. Branch filters are available to unrestricted officers; branch assignment remains enforced by the backend for restricted officers.

---

## 5. Officer — Role-Specific Functionalities

### 5.1 Administrator (`admin`) — Full Access (all permissions)
Exclusive / primary owner of:
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
- **All operational modules:** members (CRUD + approve/decline + restore/force-delete + export + password OTP + special-loan toggle), officers (CRUD + delete), branches (CRUD), ledger (CRUD + export), expenses (CRUD), loans (full lifecycle), reports (all + balance sheet download), announcements (CRUD), logs (view/export), tickets (view/edit/close/assign).

### 5.1.1 Chairman (`chairman`)
Preset: operational member, service, loan, transaction, deposit, withdrawal, branch, announcement, and report permissions; `oversight.approve`; collections review/approval; and receipt reissue. The chairman is the countersign approver for privileged actions and is subject to the same server-side branch, OTP, separation-of-duty, and workflow checks as every other user.

### 5.2 Manager (`manager`)
Preset: members (view/create/update/approve plus detail facets), services (view/create/update), loans (view/create/encode/update/approve/reject/disburse), transactions (view/view-all/create/update/approve/reversal/export), deposits & withdrawals (view/create/approve/reject), reports (view/export/financial), announcements (view), `branches.bypass`, logs (view), settings (view), tickets, notifications, profile, and collections review/approval. The default manager preset does not grant member deletion, announcement writes, officer management, or `settings.update`. Managers have cross-branch access through `branches.bypass`.
- Approve member applications (`POST /api/members/{user}/approve`, `POST /api/members/{user}/decline`).
- Approve transactions (`POST /api/services/approve/{transaction}`, withdrawal OTP `POST /api/services/transactions/{transaction}/send-otp`, `GET /api/services/pending`).
- Review/endorse/approve/reject/disburse/close loans (`POST /api/loans/{loan}/review|disburse|close`, `PUT/DELETE /api/loans/{loan}`).
- Manage permitted member, transaction, loan, ledger/expense, report, and collection workflows. Announcement writes, officer management, and settings changes require separately granted effective permissions.
- Tickets: view/edit/close/assign (the SPA direct ticket route recognizes admin/manager/accountant; the backend's support-staff predicate also recognizes a user with `tickets.manage`, so this UI/API difference must be audited rather than assumed consistent).

### 5.3 Accountant / Treasurer (`accountant`)
Preset: members (view/create/update/approve plus detail facets), services (view/create/update), loans (view), transactions (view/view-all/create/update/export — no `approve` in preset), deposits/withdrawals (view/create), announcements (CRUD), reports (view/export/financial), settings (view), tickets, notifications, profile, and collection review. No `branches.bypass`; branch access follows assignment unless the officer is assigned to Main.
- **Ledger** (`/dashboard/ledger`, `transactions.view`): `GET /api/ledger`, `POST /api/ledger`, `PUT /api/ledger/{transaction}`, `DELETE /api/ledger/{transaction}`, `GET /api/ledger/export`, `GET /api/ledger/summary`.
- **Expenses** (`/dashboard/expenses`): `GET/POST/PUT/DELETE /api/expenses`.
- **Reports:** financial, expenses, member activity/savings, loans, payments, balance sheet + download (`GET /api/reports/*`).
- Process member services where the underlying service and branch checks permit it, approve member onboarding, and view detail tabs granted by the member-detail facet permissions.
- Tickets support view (`isSupportStaff` includes accountant).

### 5.4 Loan Officer (`loan`)
Preset: dashboard.view, members.view, services.view, loans (view/create), settings.view, tickets (view/reply), notifications.view, profile (view/update), collections (view-own/create/submit). The preset does not grant `loans.encode` or `loans.update`; loan encoding, review, edits, deletes, and officer document uploads are therefore unavailable unless those permissions are explicitly granted. The admin role can encode loans through its explicit admin policy exception. No `branches.bypass`; Main-branch loan officers can select among branches in the collection route, while other loan officers remain limited to their assigned branch. **No `transactions.*`** — ledger/expenses return 403.
- **Loan Applications** (`Services → Loan Applications`, `LoanReviewModal`): loan officers can view the loan queue and product/member lookup; review and endorsement require `loans.update`, which is not in the default loan role preset.
- **Loans module** (`/dashboard/loans`, `loans.view`): `GET /api/loans/search-members`, `GET /api/loans/loan-products`, and `GET /api/loans/{loan}`. Encoding requires `loans.encode`; edits, deletes, and officer uploads require `loans.update`. Borrowers can upload documents for their own loans.
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
| Password reset | forgot/verify-token/reset routes + pages |
| Password change (OTP-gated) | Settings → My Account, `sendPasswordOtp` + `changePassword` |
| Email change (OTP-gated) | Profile → `sendEmailChangeOtp` + `EmailChangeVerification` |
| Withdrawal approval OTP | `POST /api/services/transactions/{transaction}/send-otp` (threshold `security_withdrawal_otp_threshold`, default ₱2,000) |
| Trusted devices / revoke | `POST /api/settings/revoke-devices`, `TrustedDevice` (90-day default) |
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
- Account types: `savings`, `share-capital`; transaction types: `deposit`, `withdrawal`, `loan_payment`, `loan_disbursement`, `fee`.
- Channels/modes: Counter (OTC), Online, Bank Transfer (withdrawal).

### 6.4 Loans & Loan Products (`LoansPage`, `MemberLoansPage`, `LoanProductsPage`, `loans.*`)
- Loan products: `GET/POST /api/loan-products`, `PUT/DELETE /api/loan-products/{loanProduct}`, `POST .../toggle` (enable/disable) — managed in Settings → Loan Products (`settings.update`).
- Loans: list/filter (`loan_status`, pagination), search members for encoding, view detail, create/encode, update, delete, review (`under_review`/`endorsed_for_approval`/approve/reject), disburse, close, document upload/download.
- Member loan tracker: `MemberLoansPage` + `GET /api/member-loans`.
- Guards: `profile.completed` required to apply/encode; `service.availability:loan_*`; loan-focused role hides financial cards.

### 6.5 Ledger (`LedgerPage`, `transactions.*`)
- `GET /api/ledger` (filterable ledger), `GET /api/ledger/summary`, `GET /api/ledger/export` (CSV/Excel), `POST/PUT/DELETE /api/ledger*` (`transactions.view|create|update|void|export`, `transactions.view-all` for all branches).

### 6.6 Expenses (`ExpensesPage`)
- `GET/POST /api/expenses`, `PUT/DELETE /api/expenses/{expense}` — record operating expenses, categorize, filter, view in reports.

### 6.7 Reports (`ReportsPage`, `reports.*`)
- Types (`GET /api/reports/*`): `financial`, `expenses`, `member-activity`, `member-savings`, `loans`, `payments`, `balance-sheet` (+ `download`), `options` (filter metadata).
- Controls: report-type switcher, range presets (day/week/month/year/custom), date pickers, member/loan/category/activity filters, per-report pagination, charts (Chart.js Bar/Doughnut), currency/date formatting, export (`reports.export`, `reports.financial`, `reports.audit`).

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

### 6.19 PayMongo Payment Gateway (`PayMongoPage`, Deposit/Loan Payment wizards)
- **Settings:** the current UI and API restrict gateway configuration, encrypted credentials, enabled payment methods, and connection tests to `admin`. The endpoints are `GET/PUT /api/settings/paymongo`, `PUT/DELETE /api/settings/paymongo/credentials`, and `POST /api/settings/paymongo/test`.
- **Member/officer payments:** Deposit and Loan Payment wizards fetch `GET /api/services/paymongo/methods`, create a hosted checkout with `POST /api/services/paymongo/checkout`, and allow a user-initiated status check through `GET /api/services/paymongo/verify/{transactionNumber}`.
- **Webhook:** `POST /api/webhooks/paymongo` is a public signature-verified callback; it is integration-only, not a browser UI operation. External checkout and webhook verification require sandbox credentials and must be recorded as blocked/partially verified when unavailable rather than simulated against production.

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
| SMS Service | `sms` | Admin | Configure and test SMS settings |
| Email Service | `email` | Non-members with `settings.update` | Configure and test email settings |
| Payment Gateway | `paymongo` | Admin | Configure PayMongo, credentials, methods, connection test, and webhook guidance |
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
| Withdrawals | `global`, `member`, `officer`, `counter`, `online`, `bank_transfer` |
| Loan Applications | `global`, `member`, `officer` |
| Loan Payments | `global`, `member`, `officer`, `counter`, `online` |

Disabled services block the corresponding wizard/API (`service.availability` middleware) and show `ServiceMaintenanceModal`. Optionally notifies all/members/officers via in-app notification.

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

`PermissionCatalog::groups()` is the canonical list. It includes dashboard; member detail facets (`members.view-loans`, `members.view-ids`, `members.view-activity`, `members.view-financial`); services; loan lifecycle; transaction reversal; deposits; withdrawals; announcements; reports; officers; branches; logs; settings; backups; tickets; notifications; profile; oversight; collections; and receipt reissue permissions. `logs.export` remains a declared permission without a current log-export route or UI.

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
