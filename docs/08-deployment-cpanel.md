# 08 — Deployment: cPanel + GitHub

## Pipeline

```
GitHub (main)
   │  push
   ▼
GitHub Action (optional but recommended)
   ├─ composer install --no-dev --optimize-autoloader
   ├─ npm ci && npm run build          (Tailwind/JS assets)
   └─ push the built artefacts to a `deploy` branch
   │
   ▼
cPanel → Git Version Control (clone of the repo, branch `deploy` or `main`)
   │  "Update from Remote" (manual) or a webhook-triggered pull
   ▼
.cpanel.yml deployment task → rsync into ~/public_html, run migrations, clear caches
```

Two viable shapes, decided by question 17 in `07-open-questions.md`:

- **SSH/Terminal available on the host** → keep it simple: cPanel pulls `main`, `.cpanel.yml`
  runs `composer install` and `php artisan migrate --force` on the host. No CI needed.
- **No SSH** → build in GitHub Actions and commit `vendor/` + built assets to a `deploy` branch;
  `.cpanel.yml` only copies files and runs migrations through a protected artisan web route or
  a cron one-shot.

## Directory layout on the host

```
~/repositories/csem/          ← cPanel Git clone (NOT web-accessible)
~/csem-app/                   ← deployed application (outside the webroot)
~/public_html/                ← document root → symlink/copy of ~/csem-app/public
~/csem-app/.env               ← created once by hand, never in git
~/csem-app/storage/           ← writable (775), persists across deploys
~/public_html/maps/           ← map images + generated tiles (static)
```

Point the domain's document root at `~/csem-app/public` in cPanel (Domains → edit docroot) — that
is much cleaner than the common hack of moving Laravel's `public` contents into `public_html`.

## `.cpanel.yml` (sketch)

```yaml
---
deployment:
  tasks:
    - export APP=/home/USER/csem-app
    - export SRC=/home/USER/repositories/csem
    - /bin/rsync -a --delete
        --exclude='.git' --exclude='storage' --exclude='.env'
        $SRC/ $APP/
    - cd $APP && /usr/local/bin/ea-php83 /usr/local/bin/composer install --no-dev --optimize-autoloader
    - cd $APP && /usr/local/bin/ea-php83 artisan migrate --force
    - cd $APP && /usr/local/bin/ea-php83 artisan optimize
```

Paths (`USER`, the PHP binary, the Composer path) differ per host — confirm them on the actual
cPanel account before relying on this.

## Cron Scheduler

The application requires Laravel's scheduler to run every minute in cPanel Cron Jobs:

```bash
* * * * * /usr/local/bin/ea-php83 /home/USER/csem-app/artisan schedule:run >/dev/null 2>&1
```

The scheduler handles:
- Background map refreshes (`php artisan hwrt:map-refresh --scheduled`) when cadence is set to daily, weekly, or monthly, or when requested via Admin Settings → Map.
- Automated system queue processing.

## Python & Library Requirements

Background map refreshes execute `sitemap/tools/fetch_arcgis.py` using Python 3.

- **Python Version**: Python 3.8+ (`python3`) must be installed on the cPanel host.
- **Python Dependencies**: The `Pillow` image library is required (`pip install Pillow` or cPanel Python Virtual Environment / system package `python3-pillow`).
- **Configuration**: The Python binary path can be configured in the UI under **Settings → Map** (`map.python` setting in DB, defaults to `python3`). If using a cPanel Virtualenv, specify the full binary path (e.g., `/home/USER/virtualenvs/hwrt/bin/python3`).

## Outbound Network Requirements

During normal operation, HWRT users interact **only** with local endpoints; no client-side browser traffic goes to external map servers.

For background map refreshes (manual or scheduled), the server process makes outbound HTTPS GET requests (port 443) to public ArcGIS endpoints:
- `https://enveng.maps.arcgis.com` (ArcGIS Portal item metadata)
- `https://services-ap1.arcgis.com` (ArcGIS REST FeatureServer and MapServer layers)

Ensure server firewalls or security modules allow outbound HTTPS requests to these hostnames.

## Environment (`~/csem-app/.env`, created once)

```
APP_NAME="CSEM"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://csem.example.com.au
APP_KEY=            # php artisan key:generate --show, pasted once

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=cpaneluser_csem
DB_USERNAME=cpaneluser_csem
DB_PASSWORD=…

SESSION_DRIVER=database
QUEUE_CONNECTION=database        # no daemon on shared hosting; drained by cron
CACHE_STORE=file

MAP_BASEMAP=esri_world_imagery
ALERT_THRESHOLD_HOURS=4
```

## Repo hygiene

- `main` is deployable; work on `feature/*` branches with PRs.
- Never commit `.env`, `storage/`, uploaded maps, or the tile output.
- Tag releases (`v0.1.0`) so a rollback is `git checkout <tag>` + redeploy.
- A `staging` subdomain pulling the `develop` branch is worth the 20 minutes it takes to set up
  — you do not want to test a map georeference change in production.

## Backups

```bash
# nightly, via the Laravel scheduler or a direct cron entry
mysqldump -u USER -p'PASS' DB | gzip > ~/backups/csem-$(date +%F).sql.gz
tar czf ~/backups/maps-$(date +%F).tar.gz ~/public_html/maps
find ~/backups -mtime +30 -delete
# then copy off-host (rclone to S3/Drive, or the host's own backup product)
```

An on-host backup is not a backup. Get the copy off the server.
