# HWRT improvement implementation — 28 September 2026

Companion to [product gap review](HWRT_PRODUCT_GAP_AND_IMPROVEMENT_REVIEW_2026-09-28.md). Implemented on `feature/hwrt-reports-locations-20260928` for integration into `v2/release-candidate`. Code and tests precede any production release. Run the standard update helper after advancing staging to apply the additive migrations; the helper backs up the database first.

## Available after deployment

| Feature | Location in UI | Behavior |
|---|---|---|
| Pre-store and edit reusable location | Settings → Locations → Add location / Edit | Admin enters a name, optional code/aliases, and clicks map or enters MGA50 coordinates. Area is derived from the pin. Nearby existing locations are shown. A created location is available to work operators immediately. |
| Preserve historical location | Same | Rename/move updates the catalogue for future work; existing entry label, coordinates and area stay as logged. Moving requires a reason. Open/pending jobs referencing the saved location are counted before editing. |
| Location archive/verification/audit | Same | Archive hides from the picker and prevents new entries using its ID, while history stays. Reactivation restores it. Changes, actor, reason and before/after values are stored in `location_revisions`. Aliases are searchable in the picker. |
| Multiple work characteristics | Settings → Activity tags; Log work | Admin adds/renames/retires optional activity/hazard tags. A work entry keeps one primary type and can have multiple tags. Retired tags stay on existing work and remain reportable. Other still requires a description. |
| Custom report | Reports → Build custom report | Perth date range with explicit date meaning (actually started / planned / active during period), status, work type, optional tag, area, saved location ID, free-text location name and ID/permit/notes/Other/reporter search. Select columns and summary grouping by type, Other description, tag, location, area, status or date. A tag group can count one job in several groups. CSV uses the same filters and columns; formula-like text is escaped. |
| Printable shift handover | Reports → Shift handover | Choose explicit Perth start/end times; see work open at handover, activity during the window and pending work in the following 12 hours. Print or save PDF from the browser. This is an operational snapshot, not a permit signoff. |

The existing Quick Reports and CSV remain available. The new location and tag migrations are additive. The map package is not regenerated. `public/css/app.css` is rebuilt and committed for cPanel, so the host does not need Node.

## Semantics and limits

- **Actually started** omits Pending work, because its `opened_at` field is provisional until Start is pressed. **Planned** includes only records with a planned start. **Active during** uses the job's start and close timestamps to select jobs overlapping the Perth date range; Pending and Cancelled are excluded.
- The saved-location filter uses its catalogue ID even if the name later changes. Location name search uses the snapshot saved on the entry, including ad hoc locations. Combining the two filters means both must match.
- The handover page reconstructs state from current start/close timestamps. A cancelled Pending job is not shown as open at a past handover; for a full point-in-time event reconstruction use the entry event log.
- Activity tag names are catalogue names. Renaming a tag changes its display name in old report rows; primary work type, Other description and location snapshots are retained.
- The first version of custom reports has filters, on-screen grouped counts and CSV. No saved report templates, scheduled email, category-specific permit fields, or contractor totals exist until their definitions and recipients are agreed.
- The following review items remain separate work: location merge and bulk CSV import with dry run, durable offline/idempotent submission, automatic alerting, data-quality dashboards and integration with an authorized permit system. They need source data, workflow ownership or conflict rules to be defined before implementation.

## Staging acceptance checklist

1. Confirm both new migrations ran. Check Admin-only routes reject a normal User.
2. Pre-store a pin via Settings, confirm it appears in the Log work picker; move/rename it with a reason and verify an old job keeps its old coordinates/name.
3. Archive the pin, confirm it leaves the picker and a crafted new-job request cannot use it, but its old work remains in reports. Reactivate it.
4. Add two activity tags; log a job with both and a primary type. Retire one and confirm old reports retain the association.
5. Run a custom report by each date meaning and location, then compare the displayed rows/columns with its CSV. Try an Other description and a note beginning with `=` in staging data; CSV should not execute it as a formula.
6. Set an actual site shift start/end on Shift handover, verify open, changed and pending jobs with the operator, then print the page.
7. Recheck `/board`, `/map`, `/reports`, `/settings/locations`, local CSS/Leaflet assets and logs after the update. Test restoring the pre-update SQLite snapshot in an isolated copy before any production release.
