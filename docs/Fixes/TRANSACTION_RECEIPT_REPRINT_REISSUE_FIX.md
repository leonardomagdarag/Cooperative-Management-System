# Fix record — Transaction receipt reprint & certified replacement

| Field | Value |
| --- | --- |
| Date | 2026-10-18 |
| Area | Ledger / transaction receipts (`transaction_receipts`, ledger row actions) |
| Reported | User, browser session (role: Manager) |
| Symptom | "Reprint Receipt" and "Issue Certified Replacement" reply with an error toast ("Server Error") and produce no receipt |
| Severity | High — row-specific HTTP 500, and the actions had no usable output even when they succeeded |
| Status | **Fixed and verified** in the running application |
| Related QA items | `RFT-MEM-022`, `RFT-CHM-023`, `QA-CHAIRMAN-2026-10-13-02` |
| Files changed | 2 PHP, 1 migration, 1 test, 3 React/CSS (see [Files touched](#5-files-touched)) |

## 1. Reported symptom

In the ledger grid, the row actions **Reprint Receipt** (`fa-print`) and **Issue
Certified Replacement** (`fa-certificate`) failed. The reissue action asked for a
reason through a native `window.prompt()`, then surfaced a toast reading
"Server Error" (Laravel's 500 JSON `message`) and never produced a receipt the
member or officer could hand over.

The failure was **row-specific**, not global: both actions returned HTTP 200 for
chairman, administrator, accountant, and manager on ordinary deposit/withdrawal
rows, so the buttons and their wiring were fine.

## 2. Root cause

Transactions posted by `App\Services\CollectionRunService` carry a `loan_id` but
**no `account_id`** (a collection-run loan payment is not tied to a savings
account). `TransactionReceiptController` read the member the receipt is issued to
only from the account side:

```php
'issued_to_member_id' => $transaction->account?->user_id,
```

For a loan-only payment that expression is `null`, and
`transaction_receipts.issued_to_member_id` was `NOT NULL`, so MySQL rejected the
insert:

```
SQLSTATE[23000]: Integrity constraint violation: 1048
Column 'issued_to_member_id' cannot be null
```

The exception became an HTTP 500, which `resources/react/api/client.ts` renders as
the "Server Error" toast.

Reproduction row in the local development database:
`TXN20261015000022` (transaction `id = 1270`, `loan_id = 1`, `account_id = NULL`).

## 3. Secondary defect — there was nothing to show

Even on the rows that returned 200, the two actions were dead ends because no
receipt view existed:

- `reprint` and `reissue` responded with the raw receipt record only — no
  transaction, member, or officer data to print.
- `HistoryPage.tsx` reprint expected a PDF blob the API has never returned, and
  reported the outcome through a native `alert()`, which is
  `RFT-MEM-022` (P2).
- `LedgerPage.tsx` used a native `window.prompt()` for the replacement reason,
  which is `RFT-CHM-023` (P2).

## 4. Fix

### 4.1 Backend — resolve the recipient, not just the account

`app/Http/Controllers/Api/TransactionReceiptController.php`

- New private `recipientMemberId()` resolves the member through the transaction's
  account, falling back to the owning loan:

  ```php
  $memberId = $transaction->account?->user_id ?? $transaction->loan?->user_id;
  ```

  It is used by `reprint()`, `reissue()`, and the reprint ownership check, so a
  member can still reprint a collection-run payment that belongs to their loan.
- New private `receiptPayload()` returns everything a printed copy needs, resolved
  server-side: receipt id/reason/certified/note/issued-at, issuing officer name,
  member name/member number/account number, transaction number/type/amount/date/
  status/branch/loan number/note, and the cooperative name (`APP_NAME`).

### 4.2 Database

`database/migrations/2026_10_18_000001_make_receipt_recipient_optional.php` makes
`transaction_receipts.issued_to_member_id` nullable as defence in depth, so an
unresolvable member can never 500 the action again. The column is only written,
never read for authorization, so relaxing it changes no access decision.

### 4.3 Frontend

- **New** `resources/react/components/TransactionReceiptModal.tsx` — printable
  copy with cooperative letterhead and logo, a diagonal watermarked badge
  (**DUPLICATE** for a reprint, **CERTIFIED REPLACEMENT** for a reissue), the
  transaction/member detail table, an amount band, the recorded replacement
  reason, an issuing-officer signature line, and **Print receipt** / **Close**
  actions.
- `resources/react/pages/ledger/LedgerPage.tsx` — both actions are now
  `useMutation` calls that open the receipt modal; the certified replacement
  asks for its reason in a **styled modal with validation** (minimum 5
  characters and a live character counter) instead of `window.prompt()`.
- `resources/react/pages/history/HistoryPage.tsx` — the member-facing reprint
  opens the same receipt modal instead of `alert()`.
- `resources/css/app.css` — a scoped `@media print` block so printing a receipt
  emits only the receipt paper, never the app chrome.

Both QA findings are therefore closed: `RFT-MEM-022`'s `alert()` and
`RFT-CHM-023`'s `window.prompt()` are replaced by the branded modal.

## 5. Files touched

| File | Change |
| --- | --- |
| `app/Http/Controllers/Api/TransactionReceiptController.php` | Loan fallback for the receipt recipient; printable payload for `reprint`/`reissue` |
| `database/migrations/2026_10_18_000001_make_receipt_recipient_optional.php` | New — `issued_to_member_id` nullable |
| `tests/Feature/TransactionReceiptTest.php` | New — 7 Pest cases |
| `resources/react/components/TransactionReceiptModal.tsx` | New — printable receipt view |
| `resources/react/pages/ledger/LedgerPage.tsx` | Open receipt on reprint/reissue; styled reason modal |
| `resources/react/pages/history/HistoryPage.tsx` | Member reprint opens the receipt instead of `alert()` |
| `resources/css/app.css` | Scoped `@media print` rules for the receipt paper |

## 6. Verification

Automated:

| Check | Result |
| --- | --- |
| `php -d memory_limit=512M artisan test` | 556 passed (3022 assertions) |
| `php artisan test --filter=TransactionReceiptTest` | 7 passed (37 assertions) |
| `vendor/bin/pint --test` (changed files) | passed |
| `npm run build` | succeeded |
| `php artisan migrate` (local MySQL) | migration applied |

The new Pest cases cover: reprint/reissue of a loan-only payment resolving the
member through the loan, an account-backed transaction resolving through the
account, the required reissue note, the `receipts.reissue` permission gate, member
ownership (own reprint allowed, another member's refused), and the 422 for
uncompleted transactions.

Browser (Playwright, role: Manager, against `php artisan serve` + seeded MySQL):

- Reprint renders the receipt with the **DUPLICATE** watermark, the real
  transaction number, member number, amount, branch, and issuing officer.
- Reissue renders **CERTIFIED REPLACEMENT** and prints the recorded reason; no
  native dialog fires (the styled reason modal is used).
- Under print-media emulation the document collapses to a single A4 page
  containing only the receipt paper: the app chrome computes to
  `visibility: hidden`, and hit-testing at the top of the page returns the receipt
  rather than the ledger UI.

## 7. Test data and cleanup

Browser verification created 9 rows in `transaction_receipts`
(ids 22–30, 2026-10-18). All 9 were deleted afterwards; the table was returned to
its original 6 rows. Temporary Playwright probe scripts were removed and the
verification server was stopped. No application data was otherwise modified.

## 8. Notes and follow-ups

- `receipts.reissue` exists only in the **chairman** and **manager** role presets
  (`app/Support/PermissionCatalog.php`), so `admin` and `accountant` see only
  Reprint. Whether they should also issue certified replacements is a product
  decision, not a defect, and was left unchanged.
- The receipt copy is a print view only — there is still no receipts listing page
  and no filing of the printed copy. The audit trail
  (`transaction.receipt_reprinted` / `transaction.receipt_reissued`) remains the
  record that a copy was issued.
- `vendor/bin/pint --test` fails repo-wide on
  `tests/Feature/RoleSpecificAccessBehaviorAuditTest.php` (`ordered_imports`).
  That failure pre-dates this change and is unrelated; every file touched here
  passes.
