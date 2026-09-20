# 08 — Deployment: cPanel + GitHub

The application as built has **no build step and no Composer dependencies** — plain PHP 8.1+, PDO,
and Leaflet vendored under `public/assets/vendor/`. Deployment is therefore a file copy plus a
one-off installer run; there is no `vendor/`, no `npm run build`, and no artisan cache warming.

## Pipeline

```
GitHub (main)
   │  push
   ▼
cPanel → Git Version Control  ("Update from Remote" → "Deploy HEAD Commit")
   │
   ▼
.cpanel.yml copies src/ views/ bin/ schema/ public/ into ~/cselog
   │
   ▼
~/cselog/.env  (written once by hand, never in git)
```

Because nothing is compiled, cPanel can deploy straight from `main`. No GitHub Action and no
`deploy` branch are required.

## Directory layout on the host

```
~/repositories/cselog/    ← cPanel Git clone (not web-accessible)
~/cselog/                 ← deployed application
~/cselog/.env             ← configuration + DB credentials, created once
~/cselog/storage/         ← writable (775); SQLite DB lives here if used
~/cselog/public/          ← document root for the domain
~/cselog/public/maps/     ← uploaded site maps, persists across deploys
```

Point the domain's document root at `~/cselog/public` (Domains → edit docroot). Do **not** copy
`public/`'s contents into `public_html` and leave `src/` beside it — `src/`, `views/` and the
SQLite database must sit outside the webroot.

## First install

1. Create a MySQL database + user in cPanel and grant all privileges.
2. In Git Version Control, clone `https://github.com/medrescuetech/cselog` and deploy once.
3. SSH (or File Manager) → copy `.env.example` to `.env` and fill in:

```
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Australia/Brisbane
SITE_NAME="Main Site"

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cpaneluser_cselog
DB_USERNAME=cpaneluser_cselog
DB_PASSWORD=…
```

4. Run the installer once:

```bash
cd ~/cselog && /usr/local/bin/ea-php82 bin/install.php --admin-pass='<strong password>'
```

It applies `schema/schema.sql`, seeds the work types, creates the site and the placeholder map,
and creates the `admin` user. It is idempotent — re-running it will not duplicate data.

5. Log in, go to **Admin → Maps**, upload the real site image and make it the default.
6. Draw the boundaries and landmarks in **Admin → Landmarks & boundaries**.

### No SSH on the host?

Run the installer through the browser once by temporarily copying `bin/install.php` to
`public/install.php`, hitting it, then deleting it. Nothing else needs a shell.

## Permissions

```
chmod 755 ~/cselog ~/cselog/public
chmod 775 ~/cselog/storage ~/cselog/public/maps
chmod 600 ~/cselog/.env
```

`public/.htaccess` already routes everything to `index.php` and blocks direct access to `.env`,
`*.sqlite`, `*.sql` and `*.md`. If the host runs nginx in front of Apache, confirm the rewrite
still reaches `index.php`.

## PHP version

Set the domain's PHP version to 8.1 or newer in MultiPHP Manager, with `pdo_mysql` enabled
(`pdo_sqlite` if you run SQLite in production, which is fine for a single low-traffic site but
means backups must include the file).

## Repo hygiene

- `main` is deployable; work on `devin/*` or `feature/*` branches with PRs.
- Never commit `.env`, `storage/`, uploaded maps, or tile output.
- Tag releases (`v0.1.0`) so a rollback is `git checkout <tag>` + redeploy.
- A `staging` subdomain pulling a `develop` branch is worth the 20 minutes — you do not want to
  test a map swap in production.

## Backups

```bash
# nightly cron
mysqldump -u USER -p'PASS' DB | gzip > ~/backups/cselog-$(date +%F).sql.gz
tar czf ~/backups/maps-$(date +%F).tar.gz ~/cselog/public/maps
find ~/backups -mtime +30 -delete
```

An on-host backup is not a backup. Get the copy off the server (rclone to S3/Drive, or the host's
own backup product).
