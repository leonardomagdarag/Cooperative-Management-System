# Xendit payment reconciliation

The scheduler runs `php artisan payments:reconcile-xendit` every five minutes. It dispatches one `VerifyXenditPaymentJob` per pending, unverified Xendit deposit or loan payment that has a checkout session. Jobs use the dedicated `payments` queue. This recovers missed callbacks and payments whose checkout wizard was closed before a manual status check.

The job calls the existing `XenditService::verifyAndCompletePayment()` with the checkout's recorded test/live mode. It verifies the session, successful payment status, amount including fees, currency, references, and installment allocations. A confirmed payment remains **pending** and becomes eligible for the normal officer approval queue. Reconciliation never approves a transaction or posts savings or loan balances.

- Active/unpaid sessions stay pending and are checked again on a later sweep.
- Provider-confirmed expired or canceled sessions follow the existing rejection behavior and leave the sweep.
- Provider-confirmed payments, completed transactions, rejected transactions, missing sessions, and deleted records are skipped. A job reloads this eligibility at execution time.
- HTTP failures, allowlist errors, and validation errors propagate to Laravel's queue. Each job gets three attempts, with 60-second and 300-second backoffs, and a 45-second timeout. Exhausted attempts enter `failed_jobs` and log the transaction ID. A later scheduled sweep can try the still-pending transaction again; retry exhaustion never marks a payment rejected or paid.
- Laravel unique-job locks prevent duplicate dispatches while a job is outstanding, including delayed retries. Locks expire after one hour to recover abandoned locks. The verification service also checks the payment ID under its transaction row lock, preventing duplicate verification audit/notification writes and preserving an officer rejection when requests overlap.
- Outstanding payments are still reconciled when new Xendit checkouts are disabled.

## Running locally and on shared hosting

Use the database queue (`QUEUE_CONNECTION=database`) for asynchronous execution. The existing jobs and failed-jobs tables are reused; no migration is needed. `composer dev` now listens to `payments` along with the existing queues. Restart an already-running development worker to pick up the changed queue list and job code.

The normal Laravel scheduler must run every minute:

```sh
php artisan schedule:run
```

On shared hosting, `routes/console.php` includes `payments` in its existing every-minute worker drain. The five-minute sweep is declared before that worker. Long-running workers configured outside this repository must also include `payments` in their queue list and be restarted when deploying.

To enqueue outstanding checks immediately, without waiting for the five-minute sweep:

```sh
php artisan payments:reconcile-xendit
php artisan queue:work --queue=payments --stop-when-empty --tries=3 --timeout=45
```

Delayed retries will be picked up by subsequent worker ticks; `--stop-when-empty` does not wait for delayed jobs. Queue backlog can delay checks beyond five minutes. Inspect `php artisan queue:failed`, `storage/logs/laravel.log`, and `storage/logs/queue-worker.log` when verification repeatedly fails.

Queue retries cannot bypass Xendit's API IP allowlist. Permit the verification server's current outbound public IP in Xendit developer settings. The locally inspected test payment `TXN20261110000001` remains blocked by that external setting; successful reconciliation of that real provider session has not been verified. Automated reconciliation tests use fake provider responses and SQLite; they do not contact Xendit or approve local MySQL payments.

## Implementation validation (2026-11-10)

Pint passed on all PHP files changed for reconciliation. The reconciliation, money-integrity, and ledger suites passed 51 tests (225 assertions); an additional database-worker retry test passed separately (1 test, 8 assertions). Coverage includes queue eligibility, dispatch uniqueness, paid/active/expired/canceled sessions, stale jobs, HTTP failures, actual worker backoff, overlapping verification, and preservation of officer rejection. `php artisan schedule:list` confirms the five-minute reconciliation sweep and the `payments` worker queue. No live scheduler tick or local MySQL payment reconciliation was executed as part of these tests.
