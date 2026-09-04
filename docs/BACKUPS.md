# Backups & Restore

The cooperative uses [spatie/laravel-backup](https://spatie.be/docs/laravel-backup) with two independent
tiers, each with its own schedule, scope and retention policy, configurable from
**Settings → Backup & Restore**.

| Tier | Disk | Default schedule | Default retention |
|------|------|------------------|-------------------|
| `local` | `local_backups` (`storage/app/backups`) | `0 2 * * *` (daily 02:00) | 7 days |
| `cloud` | `google` (Google Drive) | `0 3 * * 1` (weekly Mon 03:00) | 30 days |

All archives are password-encrypted (ZipCrypto) using `BACKUP_ARCHIVE_PASSWORD`
when the `backup_encryption_enabled` setting is on (default).

## Environment variables

```dotenv
# Required when encryption is enabled
BACKUP_ARCHIVE_PASSWORD=change-me-to-a-long-secret

# Optional: email notifications would have used this (emails are disabled)
BACKUP_NOTIFICATION_EMAIL=

# Optional: token for the external /backup/ping heartbeat endpoint
BACKUP_PING_TOKEN=

# Optional: Google Drive tier (can be entered from the UI instead — see below)
# GOOGLE_DRIVE_CLIENT_ID=
# GOOGLE_DRIVE_CLIENT_SECRET=
# GOOGLE_DRIVE_REFRESH_TOKEN=
# GOOGLE_DRIVE_FOLDER=
# GOOGLE_DRIVE_TEAM_DRIVE_ID=
```

### Google Drive setup

Two ways to provide the cloud-tier credentials:

1. **From the UI (recommended):** In **Settings → Backup & Restore → Google Drive
   Connection**, admins with `backups.manage` can enter the client ID, client
   secret, refresh token (and optional folder / team drive id) and click
   **Test connection**. Values are stored encrypted at rest in the
   `app_credentials` table and never returned to the browser afterwards —
   blank secret fields keep the current value, and **Remove stored** clears
   them again. Saving to the database overrides any `.env` values.
2. **Via `.env`:** set `GOOGLE_DRIVE_*` below. These act as the fallback when
   nothing is stored in the database.

To obtain credentials:

1. Create a Google Cloud project and enable the **Drive API**.
2. Create an **OAuth 2.0 Client ID** (Desktop app) client ID / secret.
3. Obtain a refresh token with the scope
   `https://www.googleapis.com/auth/drive.file` (offline access).
4. Enter or save the values (see above). The `google` disk is registered by
   `app/Providers/GoogleDriveServiceProvider.php` and resolves the credentials
   at runtime, so saved changes take effect on the next disk access.
5. The cloud tier shows as *configured* in the UI only when client ID, secret and
   refresh token are all present.

## Scheduler

`routes/console.php` registers the automatic pipeline; the Laravel scheduler
must be running on the server:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Every minute, `backup:ping` checks each tier's cron expression and retention and
runs any due, enabled tier. `backup:ping` is also exposed as the web endpoint
`GET /backup/ping?token=<BACKUP_PING_TOKEN>` (constant-time token check) so
external uptime services (e.g. cron-job.org) can trigger it over HTTPS without
shell access.

## Commands

| Command | Purpose |
|---------|---------|
| `php artisan backup:run-tier local --scope=files` | Run one tier now |
| `php artisan backup:run-tier cloud --scope=both` | Run cloud tier now |
| `php artisan backup:clean-tier local` | Enforce local retention now |
| `php artisan backup:ping` | Run any due tiers (scheduler hook) |
| `php artisan backup:restore-files --disk=local_backups --backup=Laravel/backup-....zip --password=...` | Restore only the public files of an archive |

## Restoring

Restore is **admin-only** (`backups.restore` permission) and is intended to be
rare and deliberate. From **Settings → Backup & Restore**, select an archive and
choose `db` / `files` / `both`. The API then:

1. Validates the admin's **current password**, the literal confirmation string
   `RESTORE`, and the archive's encryption password.
2. Verifies the archive exists on the chosen tier's disk.
3. Puts the app into maintenance mode (`php artisan down` with a random secret —
   this is always released in a `finally` block, even on failure).
4. Restores the database portion via the wnx `backup:restore` command (streamed
   import, schema dropped and reset first).
5. Restores user-uploaded public files (with pre-restore snapshot + rollback on
   failure).
6. Clears application caches and returns the app online.

**Never restore from an archive you did not create yourself.** A database restore
replaces current data with the archive's contents.

## Local development notes

- DB-scope backups require `mysqldump` on `PATH` (`DB_TOOLS_PATH` if it lives in
  a non-standard location). Files-scope backups work with a plain PHP build.
- The spam/audit trail lives in the `backup_runs` table and the audit log (events
  `backup.created`, `backup.failed`, `backup.cleaned`, `backup.deleted`,
  `backup.restored`, `backup.restore_failed`).
- Emails from spatie are disabled (`--disable-notifications`) because an
  unavailable SMTP channel aborts the run; failures surface in `backup_runs` and
  the audit log instead.