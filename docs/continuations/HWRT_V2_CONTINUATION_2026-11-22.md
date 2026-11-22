# HWRT V2 Continuation — 2026-11-22

**Product:** HWRT — High Risk Work Tracker
**Repository:** `medrescuetech/cselog`
**Deployed version:** `hwrt-v2-install` @ `ec3a615` (tag `hwrt-v2-working-2026-11-22`)
**Purpose:** Record the working dev deployment and the north/south map mirroring fix so
repeats of the regression are easy to diagnose.

---

## 1. Deployment state

- `dev.akgmed.org` docroot: `/home/akgmxkpo/hwrt-v2/public`
- Server checkout: `~/hwrt-v2`, branch `hwrt-v2-install`, **deployed @ `ec3a615`**
  (previously `441da35`, originally `09ac00d`; the script leaves a database + `.env`
  backup under `/home/akgmxkpo/storage/backups/` with a copy in `~/hwrt-backups` —
  download off-host).
- Same-day UI commits on top of `441da35`: `2271883` draw tools to bottom-left +
  "Hide layers" toggle; `ec3a615` pin hover tooltip shows work type
  ("type · location · elapsed"). Mirrored on `gmednet-hwrt-v2-continuation` as
  `a558697` + `1240b53`.
- Other local checkouts not served by any addon domain: `~/app.akgmed.org` (extracted
  copy, mirrored), `~/csem-review` (`staging/hwrt-v2`, mirrored), `~/hwrt` (V1 `main`).
- Git tag `hwrt-v2-working-2026-11-22` marks this verified-good state; tag
  `hwrt-v2-before-2026-11-22` marks the previous server position `09ac00d`.

## 2. North/south mirroring — what was wrong

`CRS.Simple` in Leaflet already maps +lat up the screen. The codebase used
`latLng(-n, e)` ("Simple's y grows downward"), which is correct only for viewing an
already-mirrored world: every vector layer (areas, GA sectors, roads, landmarks,
entry pins, click-to-place) rendered upside-down against the north-up imagery.

Rendered proof (screenshots in `%TEMP%\flipcheck` on the dev PC): with `latLng(n, e)`
GA1–GA13, the nine named roads and the 142 infrastructure polygons align with the
site imagery; with `latLng(-n, e)` they land mirrored over the laydown yards.

Fixes (identical on both work branches):

| Branch | Commits |
| --- | --- |
| `hwrt-v2-install` | `4572909` (sign fix), `2b90ccd` (cache-bust `hwrt-map.js` via `?v=filemtime`), `beeee19`-equivalent in `sitemap/viewer.html` still open there |
| `gmednet-hwrt-v2-continuation` | `beeee19` (same fix, plus `sitemap/viewer.html`) |

Any update built from a branch/tag that predates these commits flips the map back —
this is what made GA/road/label positions "keep breaking" after each update.

## 3. Branch divergence to resolve

`hwrt-v2-install` and `gmednet-hwrt-v2-continuation` diverged at `3209db3`:

- `hwrt-v2-install`: GA import via `map_sectors` + `landmarks` (`2ac6450`), named roads
  `3a70d38`, raster URL versioning `e387136`, fix `4572909`, cache-bust `2b90ccd`,
  declutter/POI/draw `441da35`. **This is the line running on dev.**
- `gmednet-hwrt-v2-continuation`: GA/road as `areas`+`landmarks` (`f6d1dfb`), declutter
  `9627167`, fix `beeee19`. PR #8 into `v2/hwrt` is closed; nothing tracks this branch
  to release.

Merging them needs a decision on the GA data model (`map_sectors` vs `areas`) — the
two ImportSitemap implementations will conflict.

## 4. Deploy / update workflow that worked

Update helper on server: `~/hwrt-v2/scripts/update-hwrt.sh`.

```bash
cd ~/hwrt-v2
git ls-remote --heads origin hwrt-v2-install        # verify outbound GitHub access
HWRT_OFFSITE_BACKUP_DIR=$HOME/hwrt-backups ./scripts/update-hwrt.sh
```

If GitHub is unreachable, drop `hwrt-v2-install-<from>-to-<to>.bundle` in `~`
(bundles used so far: `09ac00d→441da35`, `441da35→ec3a615` — each requires only
the starting ref, so superseding bundles can be skipped), then:

```bash
git remote set-url origin ~/hwrt-v2-install-441da35-to-ec3a615.bundle
HWRT_OFFSITE_BACKUP_DIR=$HOME/hwrt-backups ./scripts/update-hwrt.sh
git remote set-url origin https://github.com/medrescuetech/cselog.git
```

Note: scp from the dev PC timed out on port 22 while interactive SSH worked;
bundles were uploaded via cPanel File Manager (HTTPS) to `/home/akgmxkpo/` instead.

Post-update verification:

```bash
git -C ~/hwrt-v2 log --oneline -1                          # expect ec3a615
grep -n "const toLL" ~/hwrt-v2/public/js/hwrt-map.js       # expect L.latLng(Number(n), ...
```

Visual check on `/map`: GA7 (Causeway) along the south edge below South Rd;
GA11/GA12 NE; West Rd down the west boundary.

## 5. Open items / cautions

- `sitemap:import` reads `storage/app/hwrt-sitemap/current/features/` (runtime map
  package), not the repo `sitemap/`. New feature files (`ga-areas`, `site-c-named-roads`,
  `site-c-markers`) are OPTIONAL and silently skipped when absent — copy them into the
  runtime package before running `sitemap:import` if sectors/markers look missing.
- Server login shows brute-force attempts (e.g. `45.133.6.26`); SSH is password-only
  ("Server refused our key"). Set up key auth + disable password login in cPanel.
- `~/hwrt-backups` is on-host; keep a copy on the dev PC.
- Full-repo git bundle for recovery: `hwrt-v2-install-09ac00d-to-441da35.bundle` style
  bundles in `~/` on server and OneDrive; full branch backup bundle
  `cselog-backup-2026-11-22.bundle` on the dev PC.
