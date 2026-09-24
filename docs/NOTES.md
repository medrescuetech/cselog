# NOTES — running notes and rationale

## Interpretation of the brief

The brief describes a **control-room log with a spatial view**, not a permit-to-work system.
The value is: (a) nothing gets forgotten in a hole, (b) anyone can see at a glance what's open
and where, (c) there's a defensible record afterwards. Everything in the spec is measured
against those three.

## Things worth arguing about

- **The "save this location?" prompt is the whole product.** It's what turns a logging tool into
  a growing site knowledge base. It has to be one tap, and it has to be *after* submit, not
  before — never make someone name things while a radio call is live. The spec follows that.
- **Ad-hoc pins are a feature, not a fallback.** Plenty of entries genuinely are one-offs.
  Forcing everything into the catalogue is what creates the 900-row dropdown.
- **Duration is the safety signal.** The single most useful thing this system can do beyond
  logging is shout when something has been open too long. Cheap to build, high value —
  recommended for Phase 5 at the latest, ideally Phase 1.
- **Close-out discipline** is the usual failure mode of systems like this: entries get opened
  and never closed, and within a month the open board is noise. Mitigations: age escalation,
  a shift-handover report listing everything still open, and a supervisor bulk-close with a
  reason. Worth designing in from the start.

## Rejected / deferred ideas

| Idea | Verdict |
|---|---|
| QR codes at each confined space, scanned to open/close | Genuinely good — but requires physical tags and site buy-in. Log it as a Phase 6 option; the data model already supports it (`locations.code`). |
| Live GPS of the crew | Out of scope, privacy/IR implications, not asked for. |
| Websocket live updates | Not on shared cPanel. Polling is sufficient at this volume. |
| Offline-first PWA with full sync | Over-engineering for a control room. A resilient submit queue on the log form covers the real risk. |
| Native app | The PWA covers the need; a native app means store accounts and release overhead. |
| Multi-tenant SaaS | Build it single-tenant. If it turns out to be sellable to other sites later, the `sites` table already gives most of the way there. |

## Risks

| Risk | Mitigation |
|---|---|
| Base map image is poor quality / not to scale | Phase 2 works with any image; georeferencing is deferred to Phase 4 precisely so this doesn't block. |
| Shared hosting can't run Composer (no SSH) | Build `vendor/` in GitHub Actions and ship it — documented in `08-deployment-cpanel.md`. |
| Location catalogue rots | The whole of `06-location-lifecycle.md`. |
| Adoption — people keep using the whiteboard | The log form must be faster than the whiteboard. That's the one hard UX requirement; everything else can be ugly. |
| Nobody closes entries | Age escalation + handover report + supervisor bulk-close with a reason. |

## Naming

Working title **CSEM** (Confined Space Entry Monitor). The system isn't confined-space-specific
(the work type list is customisable), so something like *SiteLog* / *OpenWork* might age better.
Cheap to change now, annoying later — worth five minutes of thought before Phase 0.

## Next actions

1. GMC answers the top of `07-open-questions.md` (map data, volume, hosting specifics).
2. ~~Send whatever map image exists~~ **Done** — site map PDF and the PR25 ArcGIS Online
   web map received (`10-site-map-sources.md`). Remaining: confirm anonymous access to the
   ArcGIS tile/feature services.
3. Confirm the stack (or push back on it) and the phase order.
4. Then Phase 0: Laravel skeleton + cPanel deploy pipeline proven end-to-end with a hello-world
   page, before any feature work.

## 2026-09-21 — drawn layer solved without GMC exports

- GMC cannot export the plot plan with imagery off (Print widget burns the basemap in). Not needed:
  `sitemap/tools/render_plotplan.py` renders the Saipem plot-plan FeatureServers straight to a
  transparent PNG on the imagery's pixel grid, in the service's own colours. ~540k polylines, 1.5 MB.
- GMC's four A3 300 dpi prints are kept anyway (`sitemap/prints/`), auto-registered by
  cross-correlating their linework with the rendered plan (scale 1:3,815 → 0.323 m/px). Yara
  (east of Site C) masked out per GMC; title/legend strips cropped.
- Map assets moved out of `docs/maps` into a standalone `sitemap/` package (manifest + Leaflet
  viewer) so the map can be lifted into other projects unchanged.
