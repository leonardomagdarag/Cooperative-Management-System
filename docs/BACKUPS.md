# Backups & Restore

The cooperative uses [spatie/laravel-backup](https://spatie.be/docs/laravel-backup) with daily
on-server and off-site backup jobs. The daily schedule can be changed in the UI.

| Tier | Disk | Schedule | Retention |
|------|------|----------|-----------|
| `daily` | `local_backups` (`storage/app/backups`) | every day at the configured time (03:00 by default) | keep **30 days** |
| `offsite` | `b2` (Backblaze B2, S3-compatible) | every day at the configured time | keep **7 days** |

At the configured time the daily archive is created once locally, verified, and then copied to B2;
the off-site job never creates a duplicate database dump.
New B2 files use `backup-offsite-*`. Existing `backup-offsight-*` archives remain visible,
downloadable, restorable, and subject to retention until they age out.

All archives are password-encrypted (ZipCrypto) using the encrypted backup password stored in the
`system_settings` table. The initial installation imports `BACKUP_ARCHIVE_PASSWORD` once (or
generates a temporary strong password), after which the password can be changed from Backup &
Restore settings. The password is never returned by the API or shown after entry.
Daily and off-site backups run every day at the configured time. Keep backup copies on a local
machine as well, and store the password safely because it is required for restores.

## Environment variables

```dotenv
# Used only to initialize the encrypted database setting on first setup
BACKUP_ARCHIVE_PASSWORD=change-me-to-a-long-secret

# Optional: email notifications would have used this (emails are disabled)
BACKUP_NOTIFICATION_EMAIL=

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
operation may run at a time.

## Restoring

Restore is **admin-only** (`backups.restore` permission) and is intended to be rare and deliberate.
From **Settings → Backup & Restore**, select an archive and choose `db` / `files` / `both`. The
API then:

1. Validates the admin's **current password**, the literal confirmation string `RESTORE`, and the
   archive's encryption password.
2. Verifies the archive exists on the chosen tier's disk (including archives pulled down from B2
   on the `offsite` tier).
3. Puts the app into maintenance mode (`php artisan down` with a random secret — this is always
   released in a `finally` block, even on failure).
4. Restores the database portion via the wnx `backup:restore` command (streamed import, schema
   dropped and reset first).
5. Restores user-uploaded public files (with pre-restore snapshot + rollback on failure).
6. Clears application caches and returns the app online.

**Never restore from an archive you did not create yourself.** A database restore replaces current
data with the archive's contents.

## Local development notes

- DB-scope backups require `mysqldump`. Set `DB_DUMP_BINARY_PATH` in `.env` to the directory
  containing the binary when it is not on `PATH` (e.g. Laragon:
  `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin`). Files-scope backups work with a plain PHP build.
- The audit trail lives in the `backup_runs` table (tiers `daily` / `offsite`) and
  the audit log (events `backup.created`, `backup.failed`, `backup.cleaned`, `backup.deleted`,
  `backup.restored`, `backup.restore_failed`, `backup.offsite_connection_tested`).
- Emails from spatie are disabled (`--disable-notifications`) because an unavailable SMTP channel
  aborts the run; failures surface in `backup_runs` and the audit log instead.
- Windows error 10106 means `mysqldump` could not initialize a TCP socket. Restart the database
  stack first; if it persists, repair or restart Windows networking before retrying.
