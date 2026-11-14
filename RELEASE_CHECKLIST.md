# CSEM (cselog) Pre-Flight & Release Checklist

This checklist must be validated prior to tagging and deploying each release to staging and production.

---

## 1. Local & CI Verification
- [ ] **Dependencies**: `composer validate --strict` returns no errors.
- [ ] **Automated Tests**: `php artisan test` passes with all tests green (SQLite & MySQL).
- [ ] **Acceptance Suite**: Playwright visual acceptance tests (`npm run test:browser`) pass against running server.
- [ ] **Smoke Test**: `scripts/smoke.sh http://localhost:8000` runs through all endpoints (auth, logging, board, map APIs, history, CSV).
- [ ] **Git Worktree**: Working tree is clean (`git status` shows no uncommitted changes).

---

## 2. Release & Versioning
- [ ] **Changelog**: `CHANGELOG.md` updated with release version, date, and changes.
- [ ] **Tag**: Tag created via `./scripts/tag-release.sh v0.1.0`.
- [ ] **Push**: Tag pushed to origin (`git push origin v0.1.0`).

---

## 3. Host Environment Setup (cPanel / Server)
- [ ] **PHP Runtime**: Verified host running PHP 8.3 with extensions (`pdo_mysql`, `mbstring`, `gd`, `bcmath`, `zip`, `opcache`).
- [ ] **Directory Structure**:
  - `~/repositories/cselog` (cPanel Git Version Control clone)
  - `~/csem-app` (application deployment directory, outside web root)
  - `~/csem-app/public` (target of subdomain/domain document root)
- [ ] **Production Configuration (`~/csem-app/.env`)**:
  - `APP_ENV=production`
  - `APP_DEBUG=false`
  - `APP_URL=https://<your-domain>`
  - `APP_KEY` set (generated via `php artisan key:generate --show`)
  - `DB_CONNECTION=mysql` with dedicated database name, user, and password
  - `CSEM_ADMIN_EMAIL` and `CSEM_ADMIN_PASSWORD` defined with strong credentials
- [ ] **Filesystem Permissions**:
  - `storage/` and `bootstrap/cache/` have permissions `ug+rwX` (775)
- [ ] **Static Assets**:
  - `public/sitemap` symlink created (`ln -sfn ../sitemap public/sitemap`)
  - Tiles generated and verified in `sitemap/`

---

## 4. Staging Deployment
- [ ] Deploy to staging subdomain first using `.cpanel.yml` or manual rsync.
- [ ] Run migrations: `php artisan migrate --force`
- [ ] Run seeders (if fresh install): `php artisan db:seed --force`
- [ ] Run sitemap import: `php artisan sitemap:import`
- [ ] Cache config, routes, and views: `php artisan optimize`
- [ ] Log in with seeded admin user and verify:
  - Quick log creation
  - Open board updates and timer badges
  - Map pin rendering and layer toggles
  - User management (`/admin/users`)
  - Error viewer (`/error`)

---

## 5. Operations & Maintenance
- [ ] **Cron Setup**: Set up Laravel scheduler in cPanel:
  `* * * * * /usr/local/bin/ea-php83 /home/USER/csem-app/artisan schedule:run >/dev/null 2>&1`
- [ ] **Automated Backups**: Configured `scripts/backup.sh` in crontab (e.g. nightly at 02:00) with off-site copy sync.
- [ ] **SSL / TLS**: Verified HTTPS certificate is active and auto-renewal enabled.
