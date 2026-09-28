# HWRT V2 database backup and restore

This procedure applies to the release-candidate update helper. Backups contain user and operational data: keep them outside the web root, restrict access, and copy a verified backup off the cPanel account.

## Before an update

1. Record the current Git commit/tag and the database driver used by the installed `.env`. Identify the actual served application directory and document root.
2. Run `php artisan hwrt:backup-database` in the current application checkout. It reads Laravel's effective configured connection, including cached configuration, and writes to `storage/app/backups` by default. It exits unsuccessfully if the configured database cannot be backed up. The update helper invokes it before entering maintenance mode or changing Git refs.
3. SQLite backups use SQLite `VACUUM INTO` to make a consistent snapshot even if WAL is enabled. A file's mere existence is not considered a valid backup.
4. MariaDB uses `mariadb-dump`; MySQL uses `mysqldump`. Install/locate the relevant utility in the cPanel account before updating. The command writes a temporary mode-0600 client option file inside the private backup directory, streams a compressed `.sql.gz` dump, removes that option file, and fails closed if the dump fails. The cPanel database user must have sufficient privileges for a full dump including triggers, routines and events. No database password is placed on a command line.
5. Confirm the output filename, nonzero size and owner-only permissions. Copy it to an access-controlled location outside the host, along with the release ref and a backup of any uploaded location PDFs and runtime map package. Do not commit any of these to Git.
6. For a release update, use `git fetch --tags origin; git checkout <approved-tag>; ./scripts/update-hwrt.sh --no-git` **only after staging has rehearsed the same path**. The script takes a second fresh backup before migrations. Do not run `migrate:fresh` on existing data.

## Staging restore rehearsal

Restore to a disposable staging database, never over live data. Use a copy of the live database when available. Verify a known user, a known HRW entry, a historical record and report counts before and after restoration.

### SQLite

Take the application offline on staging. Make a separate copy of the current staging database, replace its configured SQLite file with the saved `.sqlite` snapshot, and ensure ownership and mode allow the web PHP process to write it. Restart/clear cached config if the database path changed. Run `php artisan migrate:status` and test login, map, work entries and reports.

### MariaDB/MySQL

Create a separate empty staging database and a database user with the required permissions. The dump includes `CREATE DATABASE` and `USE` statements for the original database name (`--databases`); do **not** import it as-is on a host where that original database is live. For a staging rehearsal, use an isolated server/account where the original database name cannot affect production, or review and rewrite the dump's database selection statements before import. Then run, for example:

```bash
gzip -t /private/path/hwrt-YYYYMMDD-HHMMSS-XXXXXXXX.sql.gz
gzip -dc /private/path/hwrt-YYYYMMDD-HHMMSS-XXXXXXXX.sql.gz | mysql --defaults-extra-file=/private/path/staging-client.cnf
```

Create `staging-client.cnf` with mode 0600, containing `[client]`, username, password, and host/socket details. Never paste passwords into command arguments or logs. On MariaDB, the import client may be `mariadb`. Check the pipeline's exit status (`set -o pipefail`) and inspect row counts. Point staging `.env` at the restored database, run `php artisan migrate:status`, then test representative application workflows.

## Rollback

Capture a fresh pre-update backup and the previous Git ref before deployment. If only code is at fault and no incompatible migration ran, restore the previous release and check it against the current schema. If a V2 migration changed data incompatibly, take the application offline, restore the pre-update database snapshot using the rehearsed process, switch back to the previous code ref, and verify login, entries, reports and map. Restoring a database overwrites all writes made since that snapshot: make the decision during a maintenance window and retain a copy of the failed-release database for investigation.

The update helper cannot prove that an off-host copy exists or that the dump is restorable. The staging restore and off-host copy remain required release gates. Do not deploy V2 to the live domain until these gates pass.
