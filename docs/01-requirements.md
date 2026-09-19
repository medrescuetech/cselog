# 01 — Requirements

Captured from the initial brief. `[A]` marks an assumption made in the absence of detail — each
one is a thing to confirm.

## Functional

### F1 — Log an entry
- F1.1 Record: location, work type, notes, time, status.
- F1.2 Time is **auto-captured** on submit (server clock, stored UTC, displayed in site local time).
- F1.3 Time is editable after the fact by a supervisor, with the original retained in the audit log. `[A]`
- F1.4 Work type defaults to **Confined Space Entry**; the list is customisable by an admin
  (add, rename, reorder, retire — never hard-delete a type that has history).
- F1.5 Status is **Open** or **Closed**. `[A]` A third state, *Cancelled* (logged in error), is
  recommended so mistakes don't distort duration stats.
- F1.6 Logging must be possible in well under 30 seconds while on the radio.

### F2 — Location selection
- F2.1 Search/select from previously saved locations (type-ahead, recent-first).
- F2.2 Create a new location by dropping a pin on the site map.
- F2.3 After dropping a pin: prompt **"Save this location?"** → Yes (name it, added to the
  catalogue) / No (pin belongs to this entry only).
- F2.4 The catalogue must tolerate high churn: new locations constantly, old ones retired,
  duplicates, renames. See `06-location-lifecycle.md`.

### F3 — Open list
- F3.1 List all currently open locations with elapsed time.
- F3.2 Sort/filter by site, area, work type, age.
- F3.3 Close an entry from the list or from its map pin.
- F3.4 Visual escalation for long-running entries (configurable thresholds). `[A]`

### F4 — Live map
- F4.1 Base layer is a **custom image** (site survey / GIS map).
- F4.2 Open entries render as pins; click for detail and close action.
- F4.3 Optional satellite/aerial layer beneath a semi-transparent plan overlay (requires
  georeferencing).
- F4.4 Auto-refresh for unattended display on a control-room screen. `[A]`

### F5 — Overlays
- F5.1 Define **landmarks**: named point markers (gate, muster point, tank, shaft).
- F5.2 Define **boundaries/areas**: named polygons (zones, exclusion areas, contractor lots).
- F5.3 Both are editable by drawing on the map, toggled as layers, and survive a base-image
  update where the georeference is unchanged.
- F5.4 An entry's pin can be automatically labelled with the area it falls inside. `[A]`

### F6 — History
- F6.1 Every entry, open or closed, is retained and searchable by date, location, type, person.
- F6.2 Export to CSV. `[A]` Printable shift/day report.
- F6.3 Full audit trail of create/edit/close, with actor and timestamp.

### F7 — Access
- F7.1 Authenticated users, roles: Viewer / Logger / Supervisor / Admin. `[A]`
- F7.2 Mobile-first — the form and map must work one-handed on a phone.

## Non-functional

| # | Requirement |
|---|---|
| N1 | Hosted on existing cPanel hosting; deployed from GitHub. |
| N2 | Works on patchy site connectivity — small payloads, no heavy SPA bundle, tolerant retry on submit. |
| N3 | Data retention: indefinite by default; exportable. Confirm any regulatory minimum. `[A]` |
| N4 | HTTPS enforced (cPanel AutoSSL), passwords hashed, no PII beyond names/usernames. |
| N5 | Backups: nightly DB dump retained off-host (cPanel cron + remote copy or hosting backup). |
| N6 | Single timezone per site, stored UTC. |
| N7 | Cost: hosting already paid for; no per-seat SaaS licensing. |

## Explicitly out of scope (for now)

- Full permit-to-work issuing/approval workflow, gas test readings, JSA attachments.
- Personnel-in-hole tracking / head counts / attendant rosters.
- Native mobile apps (the web app is installable as a PWA instead).
- Real-time GPS tracking of people or vehicles.
- Integration with an existing ERP/EHS system — possible later, needs its API.

These are all natural extensions; the data model deliberately doesn't block them.
