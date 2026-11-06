# Backups & Restore

The cooperative uses [spatie/laravel-backup](https://spatie.be/docs/laravel-backup) with daily
on-server and off-site backup jobs. The daily schedule can be changed in the UI.

| Tier | Disk | Schedule | Retention |
|------|------|----------|-----------|
| `daily` | `local_backups` (`storage/app/backups`) | **Daily**: 3:00 AM (GMT+8)<br>**Weekly**: 3:00 AM Monday (GMT+8)<br>**Monthly**: 3:00 AM 1st of month (GMT+8) | **GFS**: 14 Daily · 5 Weekly · 12 Monthly |
| `offsite` | `b2` (Backblaze B2, S3-compatible) | **Daily**: 3:00 AM (GMT+8)<br>**Weekly**: 3:00 AM Monday (GMT+8)<br>**Monthly**: 3:00 AM 1st of month (GMT+8) | **GFS**: 14 Daily · 5 Weekly · 12 Monthly |

At the configured time the daily archive is created once locally, verified, and then copied to B2;
the off-site job never creates a duplicate database dump.
On-demand manual backup ("Back Up Now") triggers the daily backup tier only. Weekly and monthly archives are captured automatically according to schedule.
New B2 files use `backup-offsite-*`. Existing `backup-offsight-*` archives remain visible,
downloadable, restorable, and subject to retention until they age out.

All archives are password-encrypted (ZipCrypto) using the encrypted backup password stored in the
`system_settings` table. The initial installation imports `BACKUP_ARCHIVE_PASSWORD` once (or
generates a temporary strong password), after which the password can be changed from Backup &
Restore settings. The password is never returned by the API or shown after entry.
Daily and off-site backups run every day at the configured time. Keep backup copies on a local
machine as well, and store the password safely because it is required for restores.

### Compression
Archives are compressed using PHP's native `ZipArchive` with **Level 9 DEFLATE compression** (the highest compression level). Multi-row database inserts compress by 85%–92% without requiring external CLI tools (like `gunzip`), guaranteeing cross-platform restore reliability on both Windows and Linux.

### Encryption vs. Object Lock (WORM Immutability)
- **Encryption (Confidentiality)**: All archives are password-encrypted (AES-256 / ZipCrypto) with `BACKUP_ARCHIVE_PASSWORD` to protect data privacy.
- **Object Lock (Immutability & Anti-Ransomware)**: Backblaze B2 supports S3-compatible Object Lock (Write Once, Read Many). Objects cannot be deleted or modified by any user or API key until the retention period expires.
  - Local filesystems do not support S3 Object Lock.
  - For Backblaze B2, enable Object Lock when creating the bucket and configure Default Bucket Retention (e.g., 30 days) in the B2 console.
  - During GFS cleanup, locked objects are automatically detected and preserved without breaking the cleanup pipeline.
- **Permanent Disabling of Manual Deletion**: To mitigate insider threat, accidental deletion, and ransomware sabotage, manual deletion of backups is permanently disabled across the UI, API endpoints, and countersigned oversight actions. Archives can only be pruned automatically according to the configured GFS retention schedule.

## Environment variables

```dotenv
# Used only to initialize the encrypted database setting on first setup
BACKUP_ARCHIVE_PASSWORD=change-me-to-a-long-secret

# Optional: token for the external /backup/ping heartbeat endpoint
BACKUP_PING_TOKEN=

# Backblaze B2 (off-site tier, S3-compatible)
AWS_ACCESS_KEY_ID=your-key-id
AWS_SECRET_ACCESS_KEY=your-application-key
AWS_DEFAULT_REGION=us-east-005            # your B2 S3 endpoint region
AWS_BUCKET=your-bucket-name
AWS_ENDPOINT=https://s3.us-east-005.backblazeb2.com
```

### Backblaze B2 setup

1. Create a bucket in your Backblaze B2 account.
2. Create an **Application Key** with access to that bucket.
3. Set the five `AWS_*` values above in `.env`. The `b2` filesystem disk (`config/filesystems.php`)
   uses the S3 driver pointed at the B2 endpoint; **path-style requests are forced** because B2
   requires them, regardless of `AWS_USE_PATH_STYLE_ENDPOINT`.
4. Run `php artisan config:clear` (or `config:cache`) after editing `.env`.
5. In **Settings → Backup & Restore**, the **Off-Site Storage Connection** card tests the
   connection. It shows *Connected*, *unreachable*, or *not configured*, with a manually
   refreshable **Test connection** button.

## Scheduler

`routes/console.php` registers the automatic pipeline; the Laravel scheduler must be running
on the server:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Every minute, `backup:ping` checks each tier's cron expression and queues any job that is due.
`backup:ping` is also exposed as the web endpoint `GET /backup/ping?token=<BACKUP_PING_TOKEN>`
(constant-time token check) so external uptime services (e.g. cron-job.org) can trigger it over
HTTPS without shell access.

The scheduler also runs `backup:notify-admin`. It sends a failure email to every active user with
the `admin` role when a backup reaches a failed terminal state. After the first successful daily
backup, it sends download reminders every two weeks and every month. It also checks the filesystem
containing local archives and sends one email when usage reaches the `backup_storage_warning_percent`
setting (85% by default); a new warning is sent only after usage has first fallen below the
threshold and crossed it again. Administrators must have valid email addresses, and the normal
`emails` queue worker must be running.

## Commands

| Command | Purpose |
|---------|---------|
| `php artisan backup:run-tier daily --scope=both` | Run daily job now |
| `php artisan backup:run-tier offsite --scope=both` | Run off-site job now |
| `php artisan backup:ping` | Run any due tiers (scheduler hook) |
| `php artisan backup:restore-files --disk=local_backups --backup=Laravel/backup-daily-....zip --password=...` | Restore only the public files of an archive |

## Worker

Backup and restore requests return immediately with a queued run ID. Run one dedicated worker in
production (the timeout and `retry_after` must exceed the largest expected archive operation):

```sh
php artisan queue:work database --queue=backups --tries=1 --timeout=3600
```

Set `DB_QUEUE_RETRY_AFTER=7200`. A shared `backup-operation` lock means only one archive
operation may run at a time. All workers must share `storage/app/backup-operation-cache`.

## Isolated restore verification

`php artisan backup:verify-restore` checks the latest dated daily archive
into a disposable MySQL database. It extracts uploads into a temporary directory, reads every
file, imports the SQL dump into the verification connection, and queries the restored `users`,
`loans`, and `transactions` tables. It records pass/fail in the audit log and emails active admins
on failure. It never enters maintenance mode or replaces the live database or uploads.

Create a separate database and a distinct account whose privileges are limited to that database.
For example, as a MySQL administrator (choose a strong password and the appropriate host):

```sql
CREATE DATABASE settlers_restore_check;
CREATE USER 'restore_check'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
GRANT ALL PRIVILEGES ON settlers_restore_check.* TO 'restore_check'@'localhost';
```

Set `BACKUP_VERIFY_DB_HOST`, `BACKUP_VERIFY_DB_PORT`, `BACKUP_VERIFY_DB_DATABASE`,
`BACKUP_VERIFY_DB_USERNAME`, and `BACKUP_VERIFY_DB_PASSWORD` in `.env`, then refresh config cache
if used. Do not reuse the production database name or MySQL account. The command checks both
before it connects or drops tables. Run a manual drill with `php artisan backup:verify-restore`;
`--archive=Laravel/backup-daily-YYYY-MM-DD-HH-MM-SS.zip` selects a specific local daily archive. Once configured, the
scheduler runs the latest archive every Sunday at 04:00 in the background. Ensure the normal
`schedule:run` cron is active. Failed drills leave the disposable database available for diagnosis;
the next drill resets it. Temporary extracted files are removed after each run.

This verifies that a backup can be extracted and its main tables queried. It does not prove every
business invariant or application workflow in a restored deployment. The weekly drill is skipped
until all verification database settings are present.

## Restoring

Restore is **admin-only** (`backups.restore` permission) and is intended to be rare and deliberate.
From **Settings → Backup & Restore**, select an archive and choose `db` / `files` / `both`. The
request validates the admin's current password and the literal confirmation `RESTORE`, then waits
for a different active chairman to approve it with OTP. Requesting a restore does not take the app
offline. Uploaded archives are staged on the local backup disk while the request awaits approval.

After approval, the API creates a restore run and queues an encrypted job on the `backups` queue.
The job waits 15 seconds, then creates a full local checkpoint of the current database and files
without pruning the selected source archive. If checkpoint creation fails, the selected restore is
cancelled before downtime. Active admin email addresses are captured before the restore so progress
and recovery notices can still be sent if the database changes.

After checkpoint creation, the worker:

1. Revalidates that the selected archive and checkpoint exist and the configured archive
   encryption password is usable.
2. Puts the app into Laravel maintenance mode and records a file-based restore notice.
3. Restores the database portion via the wnx `backup:restore` command (streamed import, schema
   dropped and reset first).
4. Restores user-uploaded public files (with pre-restore snapshot + rollback on failure), if selected.
5. Clears application caches and brings the app online only after all selected restore steps succeed
   and at least 30 seconds of maintenance mode have elapsed.

If a selected restore fails after the app enters maintenance, the worker attempts to restore the
checkpoint for the selected scope. On success it brings the app back online and emails admins with
the outcome. If both the selected restore and checkpoint rollback fail, it stays offline, shows a
recovery notice, and emails admins that hosting-level recovery is needed. Admin emails also report
checkpoint creation, restore completion, and failures before downtime. Email delivery depends on the
configured mail transport. The `backups` queue worker must be running for approved restores to execute.

If automatic rollback fails, do not run `php artisan up` until the database and files have been
checked; reopening a partially restored database could turn requests into server errors.

**Never restore from an archive you did not create yourself.** A database restore replaces current
data with the archive's contents.

## Local development notes

- DB-scope backups require `mysqldump`. Set `DB_DUMP_BINARY_PATH` in `.env` to the directory
  containing the binary when it is not on `PATH` (e.g. Laragon:
  `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin`). Files-scope backups work with a plain PHP build.
- The audit trail lives in the `backup_runs` table (tiers `daily` / `offsite`) and
  the audit log (events `backup.created`, `backup.failed`, `backup.cleaned`, `backup.deleted`,
  `backup.restored`, `backup.restore_failed`, `backup.offsite_connection_tested`).
- Spatie's built-in emails remain disabled (`--disable-notifications`) so an unavailable SMTP
  channel cannot abort the backup. The application sends failure and operational reminder emails
  through the normal `emails` queue after the backup state has been recorded.
- Windows error 10106 means `mysqldump` could not initialize a TCP socket. Restart the database
  stack first; if it persists, repair or restart Windows networking before retrying.
