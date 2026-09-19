# 06 — Location lifecycle

The brief calls this out explicitly: *"locations can change, lots of new ones are created etc."*
A naive dropdown becomes a 900-row mess of near-duplicates within a year. The rules below are
what keep it usable.

## 1. Creation is free, curation is deliberate

Anyone logging an entry can create a location by dropping a pin — never make someone stop and
ask an admin mid-radio-call. But new locations are born `verified = 0`. An admin's **Review**
queue shows unverified locations with their usage count, and verifying is one click. Nothing
blocks on it; it just keeps the catalogue honest.

## 2. Duplicate defence, at the moment of creation

When a pin is dropped, check for existing locations within a proximity threshold
(~15 m georeferenced, or a configurable pixel radius) **and** for a fuzzy name match
(trigram/Levenshtein on name + aliases). If either hits, prompt:

> This looks like **Sump 4 – north wall** (8 m away, used 34 times).
> [ Use that one ]  [ No, this is different ]

This one interaction removes most duplicate sprawl.

## 3. Ranking: the picker shows what people actually use

Order the type-ahead by: exact/prefix name match → recently used by *this* user → most used
site-wide in the last 90 days → everything else. In practice the top 8 "Recent" chips cover the
large majority of calls, and the full catalogue is only reached by typing.

## 4. Archive, never delete

`status = archived` removes a location from the picker while keeping it attached to its history.
Auto-suggest archiving anything unused for N months (default 12). Archived locations still
appear in history filters and can be un-archived.

## 5. Merge, don't rename-and-hope

"Pit 4", "No.4 Pit" and "Number four pit" all exist. **Merge** picks a survivor, repoints the
other locations' entries at it, copies their names in as aliases, and marks them
`merged_into_id`. Searching for any old name still finds the survivor. Merges are logged and
reversible within the audit trail.

## 6. Names change; history doesn't

Renaming updates the catalogue only. Each entry stored a `location_label` snapshot at the time
it was logged, so a report from last March still reads the way the operator wrote it, with the
current name shown alongside.

## 7. Positions change; history doesn't

Moving a pin writes a new `location_positions` row with `effective_from`. Historical entries
keep the coordinates they were logged with. Current views use the latest position.

## 8. The map underneath changes too

New survey → new `map_version`. Landmarks, boundaries and location positions belong to the map,
not the image file, so if the georeference is unchanged everything just carries over. If the new
image has a different scale or origin, the admin re-runs control points and the app offers to
re-project existing pins and overlays through the old→new transform, with a visual before/after
check before committing.

## 9. Bulk operations

A new survey or an asset-register export should not be re-keyed. CSV import/export with columns
`name, code, x, y, lat, lng, area, aliases, status`; imports run as a dry-run preview first
(create / update / conflict counts) before being applied.

## 10. Health metrics worth surfacing on the admin dashboard

- Locations created in the last 30 days, and how many were used more than once.
- Unverified count, and oldest unverified.
- Suspected duplicates (name similarity or proximity clusters).
- Unused-for-12-months count (archive candidates).
- Share of entries logged against an ad-hoc pin rather than the catalogue — if that's rising,
  the catalogue isn't serving people and the picker needs work.
