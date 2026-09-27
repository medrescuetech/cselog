# HWRT V2 deployment on cPanel

This is the V2 SSH deployment route. The application lives outside the web root and the domain's document root points at its `public/` directory. Do not copy the Laravel root into `public_html`.

## Known review environment, to verify again

The last documented review checkout is `/home/akgmxkpo/csem-review` and the host had PHP 8.3, Composer at `~/bin/composer`, SQLite and PDO MySQL. Those facts do **not** prove that `dev.akgmed.org` serves that checkout today. Confirm with cPanel Domains and SSH before updating anything:

```bash
cd /home/akgmxkpo/csem-review
pwd
git status --short
git branch --show-current
git rev-parse HEAD
php -v
command -v composer
command -v mysql
command -v mysqldump
command -v mariadb
command -v mariadb-dump
readlink public/sitemap
```

Inspect `.env` locally without pasting secrets into tickets, chat or CI logs. Confirm APP_URL, APP_ENV, APP_DEBUG, DB_CONNECTION, DB_DATABASE, DB_HOST and the actual domain document root. For a MariaDB connection, the update backup command needs `mariadb-dump`; for MySQL, `mysqldump`. If the utility or required privileges are missing, the update must stop.

## Before the V2 upgrade

1. Record the current deployed commit and database engine. Verify staging uses a separate database, `.env`, and document root from production.
2. Take a copy of the current database, `.env`, uploaded location documents, runtime map package and release ref. Store a backup outside the cPanel account.
3. Rehearse [database backup and restore](12-database-backup-and-restore.md) on a disposable staging database. Check a known account, work entry and report count after restoration.
4. Set a unique `HWRT_ADMIN_PASSWORD` of at least 16 characters in the production `.env` before migrations and config caching. The production bootstrap command refuses the default `admin` password; the new Admin must still rotate the configured password at first login. Keep this value out of Git and logs.
5. Verify the installed `public/sitemap` is the tracked symlink to `../storage/app/hwrt-sitemap/current`. If the older installation has a real directory, plan its migration explicitly; the update helper stops rather than replacing it.
6. Confirm local CSS, Alpine and Leaflet assets are present in the release checkout. No Node build is required on the host when the committed assets are current.

## Stage the exact release candidate

Use a staging checkout and do not change the live domain while testing. Once the V2 PR has a final approved SHA or tag, fetch that exact ref. The update script must be run from the checked-out V2 code because the old V1 checkout does not contain its backup command:

```bash
cd /path/to/staging-app
git status --short
git fetch --tags origin
git checkout <approved-v2-ref>
PHP_BIN=/path/to/php83 COMPOSER_BIN=/home/akgmxkpo/bin/composer ./scripts/update-hwrt.sh --no-git
```

The helper creates a database backup before maintenance mode, seeds the versioned runtime map if needed, runs Composer, migrations, bootstrap-admin, sitemap import and caches, and exits maintenance mode on script failure. It refuses an existing real `public/sitemap` directory. Use the confirmed PHP binary; no sudo is required.

Run the script twice on staging. Check `/login`, `/board`, `/map`, `/pending`, `/reports`, `/settings/users` and `/error` over HTTPS. Test a normal User and an Admin, password rotation, one work entry and close action, reports/CSV, map imagery/plan alignment, and the Support link. Check `storage/logs` and the browser console. Then rehearse restoration of the pre-upgrade database and prior code ref on the disposable staging copy.

## Scheduler and map refresh

If scheduled map refresh is enabled, install a cPanel cron entry with the **verified** PHP 8.3 path:

```cron
* * * * * /path/to/php83 /path/to/app/artisan schedule:run >/dev/null 2>&1
```

Background map refresh uses Python 3, Pillow and outbound HTTPS to the configured public ArcGIS sources. Check those requirements before enabling automatic refresh. Normal browser map use serves local assets and does not need ArcGIS. A disabled refresh schedule does not require Python/Pillow for ordinary work logging.

## Production release

After staging acceptance, merge PR #10 into `main`, set `VERSION` to `2.0.0`, tag the approved commit `v2.0.0`, and confirm CI for that exact SHA. Agree on a maintenance window and take fresh, verified off-host backups. Update the production checkout to the release tag with the same `--no-git` process rehearsed on staging. Confirm the database backup command succeeded before migrations. Smoke test login, users, work logging, closing, map and reports, and record the deployed SHA and backup reference.

Do not assume code rollback reverses a migration. A data rollback restores the pre-update database and earlier code together, and loses writes made after that snapshot. See [database backup and restore](12-database-backup-and-restore.md).

## Environment notes

Production `.env` stays outside Git and uses `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` for the real HTTPS domain, and the verified database connection. Keep the existing `APP_KEY` when upgrading: changing it invalidates encrypted application data and sessions. The domain document root must point at the app's `public/` directory; `storage/` and `.env` must not be web-accessible.
