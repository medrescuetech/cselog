# 05 — Workflows and UI

Mobile-first. Five screens do everything: **Log**, **Map**, **Open board**, **History**, **Admin**.

## S1 — Log an entry (the 30-second screen)

```
┌──────────────────────────────┐
│ ● Log entry        23:41 ⟳   │   time auto, tap to adjust
├──────────────────────────────┤
│ Type                         │
│ [Confined Space Entry ▾]     │   default preselected, list admin-managed
├──────────────────────────────┤
│ Location                     │
│ [ search…                 🔍]│   type-ahead over catalogue + aliases
│  ⟳ Recent                    │   last 8 used, biggest tap targets
│   • Sump 4 – north wall      │
│   • Pit 12 access            │
│   • Vessel T-301             │
│  ＋ New location (drop a pin)│
├──────────────────────────────┤
│ Notes                        │
│ [ 2 crew, Jacobs, gas tested]│
├──────────────────────────────┤
│ Reported by  [ Ch.2 – Dave ] │   optional
│                              │
│        [  SUBMIT  ]          │
└──────────────────────────────┘
```

- Time is captured server-side at submit. The clock display is live so the operator can see
  what will be recorded; tapping it opens a "logged late — actual time was…" adjuster.
- Submitting with an existing location → straight to the **Open board** with the new row
  highlighted, and the pin already on the map.
- Submitting with **New location** → the **Pin drop** screen.
- The form survives a dropped connection: it's kept in `localStorage` and retried, so a submit
  on bad site wifi is never silently lost.

## S2 — Pin drop + "Save this location?"

```
[ site map, pan/zoom, landmarks + boundaries visible ]
             ✛  crosshair centred; "tap to place, drag to adjust"

  ┌────────────────────────────────────┐
  │ Save this location for next time?  │
  │ It'll be selectable from the list. │
  │                                    │
  │ Name  [ Sump 4 – north wall      ] │   ← only shown on "Yes"
  │ Area: Tank Farm B (auto)           │   ← from point-in-polygon
  │                                    │
  │      [ No, just this once ]  [Yes] │
  └────────────────────────────────────┘
```

- **Yes** → a `locations` row is created (`verified = 0`), the entry links to it, and it appears
  in the picker immediately for everyone.
- **No** → the pin is stored on the entry only (`location_id = null`), and the entry shows with
  a dashed marker. It can be promoted to the catalogue later from the history view — useful when
  the same "one-off" turns up for the third time.
- If the dropped pin is within ~15 m (or N px) of an existing location, warn:
  *"This is close to **Sump 4 – north wall**. Use that instead?"* — the main defence against
  duplicate sprawl.

## S3 — Live map

- Header: site selector, map/layer selector, "N open" counter, last-refresh indicator.
- Layers panel: satellite (if georeferenced), site plan opacity slider, landmarks, boundaries,
  open pins, (history playback — later phase).
- Tap a pin → bottom sheet: location, type, elapsed, notes, who logged it →
  **[Close entry]** / **[Edit]** / **[Directions]** (if georeferenced).
- **Wallboard mode** (`/map?wallboard=1`): no chrome, auto-refresh, big pins, the open list
  docked on the right, red flashing for overdue. Designed for a control-room TV.

## S4 — Open board

```
OPEN (5)                            [ + Log entry ]   [ Map ]

⏱ 4:12  ● Vessel T-301        CSE    Tank Farm B   2 crew, Jacobs      [Close]
⏱ 2:47  ● Sump 4 – north wall CSE    Pond area     gas tested 09:10    [Close]
⏱ 0:58  ● Pit 12 access       CSE    Yard          —                   [Close]
⏱ 0:31  ◌ Weighbridge chamber Other  Gate 1        drain inspection    [Close]
⏱ 0:06  ● Silo 2 manway       CSE    Plant         —                   [Close]
```

- Sorted by age descending (oldest = most at risk, at the top).
- Row colour escalates with age; a bell icon appears past the alert threshold.
- Group-by toggle: none / area / work type.
- Close asks for an optional note and stamps `closed_at`, `closed_by`.

## S5 — History

- Filters: date range, site, area, location, work type, status, logged-by.
- Columns: opened, closed, duration, location, type, area, notes, logged by, closed by.
- Row expands to the full audit trail (`entry_events`).
- **Export CSV** and **Print shift report** (a day's entries, durations, still-open at handover).
- *Playback* (later phase): scrub a slider across a day and watch pins open and close on the map.

## S6 — Admin

| Tab | Contents |
|---|---|
| Work types | Add/rename/reorder/retire; set default; "requires note" flag; colour. |
| Locations | Table with search, usage count, last used, verified flag. Actions: verify, rename, add alias, move pin, archive, **merge into…**, CSV import/export. |
| Maps | Upload a new map version, set current, run georeferencing (control-point picker with live RMS), tile large images. |
| Overlays | Draw/edit landmarks and boundaries; import GeoJSON/KML. |
| Users | Invite, set role, deactivate. |
| Settings | Site timezone, age thresholds, basemap choice, alert recipients, retention. |

## Accessibility & field realities

- Minimum 44 px tap targets; works one-handed with gloves on a phone.
- High-contrast default theme, plus a dark theme for night shift in the control room.
- No dependence on hover — everything works by tap.
- Total page weight target < 300 KB on the log screen; map tiles loaded lazily.
- Installable as a PWA so the log screen opens from the home screen and caches its shell.
