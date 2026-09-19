# 07 — Open questions

Ordered by how much the answer changes the build. Nothing here blocks starting Phase 0–1.

## Blocking for the map work (Phase 2–4)

1. **What map data exists?** Tick all that apply: JPEG/PNG plan · PDF plan · GeoTIFF · shapefile
   / DWG / DXF · ArcGIS or QGIS project · drone orthophoto · nothing but a screenshot.
   *If any georeferenced file exists, the satellite overlay becomes trivial and accurate.*
2. **How big is the image / how many sites and maps?** One site, one plan, or a dozen plans
   across several sites (and levels/floors within a site)?
3. **Is there an existing satellite/aerial subscription** (Nearmap in particular), or should we
   default to free Esri World Imagery?
4. **Real-world accuracy needed?** "Roughly the right corner of the yard" vs "within a metre" —
   this decides whether a 3-point affine fit is fine or we need proper survey control.

## Blocking for scoping

5. **Volume.** Entries per day, and typical/peak concurrent open entries.
6. **Who logs?** One controller at a desk, several control-room staff, or field crews on phones?
   Shared account or named users? (Named users is strongly recommended for an audit trail.)
7. **Connectivity** where logging happens — office wifi, or 4G with dead spots? Decides how much
   offline tolerance is worth building.
8. **Does this replace or feed an existing permit-to-work / EHS system?** If it must interoperate,
   we need that system's export or API early.
9. **Retention and audit obligations** — how long must records be kept, and does anything need to
   be tamper-evident or signed off?

## Nice to settle early

10. Alerting: should an entry still open after N hours email/SMS someone? Who, and at what
    threshold? (SMS on cPanel means a gateway — Twilio/MessageMedia — and a small cost.)
11. Shift handover: is a printed/emailed "still open at handover" report needed?
12. Should closing require a second confirmation from the crew (radio call back), or is the
    controller's word enough?
13. Do you want the additional fields — permit number, contractor/crew, number of people in the
    space, gas-test time — now or later? (Cheap to add now, awkward to backfill.)
14. Branding: GMC logo/colours, and what domain or subdomain it lives on.
15. Who administers it day to day (work types, locations, users)?

## Hosting specifics needed before deployment

16. cPanel host and PHP version available (needs 8.2+).
17. Whether **SSH/Terminal** is enabled on the cPanel account — that decides whether Composer
    runs on the host or whether `vendor/` is built in CI and shipped.
18. MySQL version, and whether we can create a new database + user.
19. Domain/subdomain and SSL (AutoSSL presumably).
20. Cron availability (for the scheduler and nightly backups).
