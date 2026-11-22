# HWRT map: status, problems and proposed fix

**Snapshot:** 2026-11-22 13:39 AWST (Perth)
**Author:** Devin, for agill

---

## 1. Current state

### Branches (git, verified at time of snapshot)

| Branch | Head | On origin | Notes |
|---|---|---|---|
| `hwrt-v2-install` | `2b90ccd` (cache-bust for orientation fix) | Yes, at `2b90ccd` | **Main line from here on.** Has 49 commits that `gmednet-hwrt-v2-continuation` doesn't, including `4572909` (fixes the north/south mirroring). |
| `gmednet-hwrt-v2-continuation` | `9627167` (map declutter and annotation tools) | Yes, in sync | Has 4 commits since the common base `3209db3`: `bca37c2`, `5ea87e6`, `f6d1dfb`, `9627167`. **Missing the mirroring fix and the newer map data model.** |

### Local worktrees

- `gmednet-shiny-adventure` (`gmednet-hwrt-v2-continuation`) has an uncommitted partial port of the orientation fix: `toLL`/`fromLL` in `hwrt-map.js` and `csem-map.js`, a `?v=` cache-bust in 3 views, and `tests/Browser/map.spec.js`. It's no longer needed if this branch is retired (see 3.4).
- `hwrt-v2-install` has the rebuild (see 3.2) **complete, reviewed and committed locally, but not pushed**: 11 modified files plus 2 new ones (`database/migrations/2026_11_22_000002_map_declutter.php`, `public/js/hwrt-draw.js`).

### Server (`~/hwrt-v2`), not verified directly

- Before today it ran `hwrt-v2-install` plus hand-applied update files, which were stashed later (8 modified and 3 untracked).
- Today it was updated with `update-hwrt.sh --target gmednet-hwrt-v2-continuation`. That moved it onto the older line and ran migration `2026_11_22_000001_map_display_defaults`.
- Restore commands were issued (see 3.1). **The user hasn't yet confirmed they were run or that they worked.**

---

## 2. Problems

| # | Problem | Cause | Severity |
|---|---|---|---|
| P1 | GA areas, roads and labels are drawn in the wrong place, mirrored north/south | The deployed branch lacks `4572909`. Its `toLL` negates northing, but `CRS.Simple` already flips the y-axis, so vectors are mirrored about each raster's centre while the imagery stays upright. | High: map is misleading |
| P2 | Server lost features that `hwrt-v2-install` had: map sectors (admin-editable GAs), named-road import, site markers and emergency markers, saved and default map views, work tags, custom and handover reports, CSV location import, demo dataset, raster URL versioning | The server was moved to a branch 49 commits behind | High |
| P3 | Extra DB rows from the continuation branch's import: `areas` of kind `ga` and `road` with the old geometry, and landmarks recategorised to `ga`/`road`. GA colour was changed by migration `…000001`. | `f6d1dfb` and `9627167` import logic and migration | Medium: causes clutter and duplicate labels. No data loss. |
| P4 | The declutter, POI, draw-tool and pin work in `9627167` was built on the wrong branch | Work started from `gmednet-hwrt-v2-continuation` instead of `hwrt-v2-install` | Medium |
| P5 | The server worktree has uncommitted hand-applied files, and `update-hwrt.sh` refuses to run on a dirty worktree | Earlier manual patching | Medium: blocks normal updates |
| P6 | PHP/Composer aren't on PATH locally. **Worked around:** PHP 8.3 at `C:\Users\agill\tools\php-8.3.36`, and `composer install` was run in the `hwrt-v2-install` worktree. `HwrtV2Test`: 14/15 pass. The one failure, `test_sitemap_import_stores_local_road_features_when_available`, also fails on the base commit `2b90ccd`: its expectation went stale when `3a70d38` renamed the CAD roads to `road_design`. | Local environment | Low: one stale test to fix later |
| P7 | "Points with no end date" was listed as a requested change but isn't in `9627167` | Requirement is unclear | Low: needs clarification |

---

## 3. Proposed fix

### 3.1 Restore the server now (fixes P1, P2, P3)

```bash
cd ~/hwrt-v2
git stash list                       # confirm the stash with the hand-applied files exists
git checkout hwrt-v2-install
git stash pop
git status --short                   # expect the same 8 modified + 3 untracked files as before

php artisan tinker --execute="
  DB::table('areas')->where('kind','ga')->update(['active'=>false]);
  DB::table('landmarks')->whereIn('category',['ga','road'])->update(['active'=>false]);
  DB::table('migrations')->where('migration','2026_11_22_000001_map_display_defaults')->delete();
"

php artisan sitemap:import --path=storage/app/hwrt-sitemap/current
php artisan optimize
```

Then hard-refresh `/map` with Ctrl+F5. The tinker step only **deactivates** rows and can be undone. Nothing is deleted except the migration's record in the `migrations` table.

### 3.2 Rebuild today's map changes on `hwrt-v2-install` (fixes P4): done, committed locally, not pushed

1. **Layer defaults:** Nearmap whole-site imagery and "Named infrastructure (142)" are off by default but can still be turned on.
2. **Infrastructure declutter:** infrastructure polygons and their labels move to their own overlays, off by default.
3. **GA sectors:** brighter border (`#a3e635`, weight 3, no fill) with permanent centred labels. Migration `2026_11_22_000002_map_declutter` recolours only sectors still on the old import default (`#d336c6`), so admin-customised colours are kept.
4. **Road labels:** dark bold text on a light pill, still rotated. No dot under the label.
5. **POI types:** ERT pickup, AED, first aid, LV rescue, muster point, obstruction, hydration, gas detector, works and risk. They're defined once in `Landmark::POI_TYPES`, shown as glyph mini-dots in a "Points of interest" overlay, and selectable in Settings → Map overlays.
6. **User quick-add and remove on `/map`:** `POST /api/landmarks` and `DELETE /api/landmarks/{id}`. Users can only delete points they added (`source = user`).
7. **Draw overlay** (`hwrt-draw.js`): line, area, point and erase, saved per browser. POI placement is ignored while drawing.
8. **Pins:** adjustable entry-pin size on `/map` and on the new-entry pin picker, with deeper zoom (`/map` max 6, picker max 8).
9. **Tests** added to `tests/Feature/HwrtV2Test.php` for POI create, validation, delete permissions and settings types.

### 3.3 Release (after 3.1 is confirmed and 3.2 is reviewed)

1. Review the diff, run feature tests (needs a working PHP, see P6) and `node --check`.
2. Commit and push to `origin/hwrt-v2-install`.
3. On the server, compare the hand-applied files against the branch first (`git diff`, `git stash show -p`). If they're already in the branch, discard them so the worktree is clean (fixes P5).
4. Deploy with `./scripts/update-hwrt.sh` while on `hwrt-v2-install`. **Don't use `--target gmednet-hwrt-v2-continuation`.**
5. Verify: GAs line up with the GA print, road names sit on the roads, emergency markers are on the equipment, the POI add and remove round-trip works, layer defaults are correct, and the entry pin lands where it was placed.

### 3.4 Clean up

- Stop deploying `gmednet-hwrt-v2-continuation`. Keep it as a reference until 3.3 has been verified, then archive it.
- Throw away the uncommitted orientation patch in the `gmednet-shiny-adventure` worktree (needs user confirmation).
- Clarify P7 ("points with no end date") and schedule it if still wanted.

---

## 4. Open items for the user

- [ ] Confirm the server restore (3.1) worked and positions are correct.
- [ ] Explain what "points with no end date" should do.
- [ ] Approve discarding the uncommitted patch in the continuation worktree.
