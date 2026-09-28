# HWRT product gap and improvement review — 28 September 2026

**Review target:** `v2/release-candidate`, following the `dev.akgmed.org` V2 rehearsal. **Status:** design recommendations, not implemented requirements. This review does not change the deployment checklist or make a permit-to-work approval claim.

## 1. Intended product and boundary

The initial brief (`docs/01-requirements.md`, `docs/06-location-lifecycle.md`) envisioned a fast radio-friendly register of live high-risk work, with searchable historical entries, a curated reusable location catalogue, an optional map for situational awareness, auditability, and cPanel hosting. `docs/V2.md` expands CSEM into HWRT: configurable work types, permanent HRW ID, Pending/Logbook/Reports, users and settings. The application records and displays work; it does **not** issue, authorize, or close statutory permits or replace a site SWMS, entry permit, JSA, isolation, rescue plan or incident system.

That boundary matters. Safe Work Australia lists many high-risk **construction** activities beyond the initial HWRT defaults (for example work near traffic or moving plant, demolition, work over water, and work involving pressurised gas distribution mains). The applicable activity may have several hazards at once. WorkSafe WA explains that a SWMS is required for high-risk construction work, including construction on a mine site. These sources guide the catalogue discussion; they do not establish that every recorded job requires the same permit. [S1–S3]

As a comparison, Intelex's published permit-to-work product combines configurable work/hazard types with approval, authorization, JSA links, PPE/controls, and visibility of overlapping work in an area. HWRT currently has the register, scheduling and area/map view, but not those approval and control workflows. Treat Intelex as a feature comparator, **not** as a compliance baseline or a reason to make HWRT a full permit system before launch. [S4]

## 2. Observed features versus stated intent

| Capability | Current V2 behavior found in code | Gap / consequence | Recommended direction |
|---|---|---|---|
| Log any work | Admin-configured `work_types`; one `Other` type requires a description; each type can require Notes and show a prompt (`WorkTypeController`, `EntryController`). | A work type is a single primary category. Multi-hazard work cannot be classified across multiple types; `Other` remains a reporting bucket even though its description is exported. | Keep primary type and `Other` for launch; later add optional hazard/activity tags, with controlled promotion of frequent Other descriptions to new types. |
| Live work | Open Board shows age, type, location, area, permit, operator and close action; Pending shows future jobs (`entries/board.blade.php`, `entries/pending.blade.php`). | The board has no explicit handover snapshot or combined location conflict warning. | Add a shift handover view and an informational overlap indicator for simultaneous work, after operator review. |
| Reports | Date/status/type/area/text filters, totals by status, paginated details, CSV with `other_type`, notes, duration and people (`ReportController`). | No printable/shift report, per-type/area/contractor trend, scheduled-versus-actual exception report or recurring export. The UI table does not display Other as a separate column but renders its description in Type. | Add a defined handover report first, then recurring aggregate reports. Ensure on-screen/CSV filter and timezone parity. |
| Saved locations | Log form can search active catalogue, create from a dropped map pin or log an ad hoc pin; nearby lookup warns by distance (`LocationController`, `entries/create.blade.php`). Settings lists locations, verification and private PDFs (`LocationSettingsController`). | Cannot pre-store or edit a catalogue location in Settings; cannot change name/code/pin/area or archive/merge through UI. Historical coordinate/name snapshots already exist on entries. | Add dedicated Admin Create/Edit (form and map), archive/reactivate, position history and auditable changes. |
| Catalogue quality | Active-only picker and basic MRU; 15 m proximity check; verified flag; schema has status and `merged_into_id`. | No name similarity/alias lookup, merge workflow, position history, CSV dry run, or review of stale locations despite `docs/06-location-lifecycle.md`. | Phase curation features as below; do not blindly edit historical entry snapshots when correcting a catalogue pin. |
| Permissions | User/Admin only; Admin manages types, users and locations; entry event log exists. | Admin settings changes to work types/locations are not represented by an equivalent history of field changes. | Record actor, time, old/new values and reason for location changes and work-type retirement. |
| Connectivity | Lightweight local JS/CSS/map assets. | Initial brief's tolerant retry/offline submission has no durable queued submission or idempotency key. A retry after a timeout could double-log a job. | Add idempotent create requests and a clear 'submission unknown; check board' recovery state before any offline queue. |

### Map versus operational information

The map answers **where**; the board and reports answer **what, when, by whom, and what remains outstanding**. Keep the underlying entry with HRW ID, time, status, type, permit reference and audit history as the source of truth. Map layers are useful context but can be stale after a survey/package update. Display map package date/accuracy near the map, and keep the selected entry's coordinate/name snapshot independent of later catalogue edits. Do not interpret the map as proof that a permit or safe-work control is active.

## 3. Additional work types and 'Other'

**Launch-safe behavior already exists:** Admin can create or retire types without code; users can select Other and enter a description. The initial set (confined space, hot work, heights, excavation, electrical isolation, inspection, Other) is only an example in `docs/V2.md`. Before live use, agree the site's actual operational categories with the relevant work controller. Potential candidates to discuss include lifting/crane operations, work near moving plant, demolition, line breaking/pressure systems, work over water, energised electrical work, and fire system impairments. These are examples to assess, not an automatic legal or permit classification. [S1–S3]

Proposed data approach:

1. Keep one **primary work type** so existing filters and counts remain stable.
2. Keep the required free-text description for Other. Report Other by description as well as total, and highlight common descriptions for Admin review. Never silently remap old entries when a new type is created.
3. Consider optional multi-select **activity/hazard tags** (separate from primary type), so work in the same area can be grouped across types without replacing current records. Define tags with site owners, not by assuming a universal risk matrix.
4. Permit number remains a reference to the authoritative external permit; optional external URL/document reference can follow only after access and retention rules are settled.
5. Each type's required fields should be a minimal, configurable prompt. Adding category-specific mandatory compliance fields without the operational owner risks implying HWRT is an approved permit process.

## 4. Reporting roadmap

**P1 — shift handover / operational report:** one printable page or PDF showing open now, pending during next shift, opened/closed/cancelled during a selected Perth shift window, overdue/age bands, and exceptions (late log, missing permit where the site expects one). Include generation time, applied filters, HRW ID, last event, location, type/Other description, permit, reported by and handover notes. Define the local shift start/end with users; do not assume midnight or a universal 12-hour roster. An export must retain the exact same result set as the displayed report.

**P1 — reliable counts:** document whether 'total' means jobs opened in period, jobs active at any point in period, or events recorded in period. Those are different measures. Current `ReportController` filters `opened_at`, including a provisional planned value for Pending entries; this can mislead a 'work performed' count. Define separate metrics and test jobs spanning the date boundary, Pending→Open, late entry, cancellation, and Perth timezone.

**P2 — management trends:** counts and duration by primary type, Other description, area, contractor/crew if a structured contractor field is later added, time-of-day and week. Show both job count and distinct locations; compare against a chosen previous period without implying causation. Add archive-safe and anonymized exports where necessary. CSV currently contains free-text fields; protect spreadsheet exports against formula interpretation and test Excel opening before wider distribution.

**P2 — data quality:** unverified/stale/duplicate locations; Other share; late logs; incomplete expected references; map age; jobs left open unusually long. These are review queues, not performance or compliance scores by default.

**P3 — automation:** scheduled handover email and dashboard only after recipient list, cron reliability, information sensitivity and report definitions are agreed. Do not email full operational maps/notes by default.

## 5. Pre-store and edit locations

**First build (P1):** add Settings → Locations → Add location and Edit. Admin enters name, optional code, area (derive from point; show override only with reason if needed), and location by map click or validated MGA easting/northing. Show map CRS and bounds; preview the pin and nearby active locations before save. Allow pre-created locations to be verified immediately by an Admin; field-created pins remain unverified. Enforce uniqueness for names/codes in an agreed scope, check proximity, and link to source/description when helpful.

**Editing safeguards (P1):** editing name or position updates the catalogue for future selection while retaining the immutable entry `location_label`, `easting`, `northing`, `area_id` snapshots already saved at logging. Before a move, show affected open/pending jobs and require a reason; clearly decide whether open jobs keep the logged pin (recommended) or need an explicit job correction. Record old/new values, actor and timestamp. Recalculate catalogue area from new coordinates while keeping historical entry area intact. Confirm handling for an old map package versus a new survey reference.

**Curation (P2):** archive/reactivate (never hard-delete referenced locations), aliases and merge with dry-run showing impacted entries, and a review queue for nearby/name duplicates. A merge should preserve each job's original label/coordinates and make alias searches resolve to the survivor. Add bulk CSV preview/import only when source coordinates and CRS can be validated; reject malformed/out-of-bounds rows rather than converting silently. These items are described in `docs/06-location-lifecycle.md` but are not yet available through Settings.

**Acceptance checks:** (a) Admin pre-stores a location and a User finds it without logging an earlier job; (b) Admin moves/renames a location and new jobs use the new pin while old reports show their original snapshots; (c) near-duplicate prompts appear; (d) active open job behavior is unambiguous; (e) archive removes from new-job picker but not historical reports; (f) normal Users cannot edit the catalogue; (g) document downloads stay authenticated and private.

## 6. Sequence and launch decision

| Priority | Recommendation | Launch impact |
|---|---|---|
| P0 | Finish current staging acceptance: board, map, Other work entry, Pending→Start→Close, report/CSV, permissions, backup/restore and production deployment gates. Confirm version/tag and site-owned work type list. | Required before calling V2 live. |
| P1 | Admin pre-store/edit location with audit and historic snapshots; shift handover report with defined shift window and time/count semantics. | Strong candidate for the first post-launch release; bring into launch only if operations cannot run without them. |
| P2 | Catalogue archive/merge/alias/import; structured multi-hazard tags; trends and data quality reports; idempotent submissions. | Plan and iterate with real users after staging feedback. |
| P3 | External permit system links, automated distribution, approval/authorization, offline queue, wallboard role. | Separate scoped decisions; permit authorization needs process ownership and assurance. |

**Product questions for the owner:** Which site work categories and shift windows must be reflected on day one? Does HWRT remain an awareness register with a permit reference, or is there an approved mandate to issue permits in it? Who owns location corrections and can a saved pin represent a moving asset? Are reports for live control, shift handover, contractor statistics, or formal compliance? Answers drive the P1 scope; none requires delaying the current staging review.

## Sources and evidence

- Repository: `docs/01-requirements.md`, `docs/V2.md`, `docs/06-location-lifecycle.md`, `docs/07-open-questions.md`; `app/Http/Controllers/{Entry,Location,LocationSettings,Report,WorkType}Controller.php`; `resources/views/{entries,settings,reports}`; migrations `2026_09_21_000001` and `2026_09_25_000002` (reviewed 28 Sep 2026).
- [S1] Safe Work Australia, [High risk construction work requiring a SWMS](https://www.safeworkaustralia.gov.au/duties-tool/construction/hazards-information/high-risk-construction-work-requiring-swms).
- [S2] WorkSafe WA, [Safe work method statements for high risk construction work](https://www.worksafe.wa.gov.au/publications/safe-work-method-statements-high-risk-construction-work) and [Construction on a mine site](https://www.worksafe.wa.gov.au/construction-mine-site).
- [S3] WorkSafe WA, [Managing the risks associated with confined spaces](https://www.worksafe.wa.gov.au/managing-risks-associated-confined-spaces). Confined-space entry permits have requirements beyond recording a job on a map.
- [S4] Intelex, [Permit to Work Software](https://www.intelex.com/products/applications/permit-work-software/) (vendor's own feature description, used as a comparison only).
