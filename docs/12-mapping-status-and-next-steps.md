# 12 — Mapping Status and Next Steps

This document summarizes the current status of the HRWT (High Risk Work Tracker) mapping engine and outlines prioritized upcoming development tasks.

---

## 1. Mapping System Architecture & Current Status

The HRWT mapping engine is fully implemented and operating on real site GIS data using Leaflet.

### Coordinate System
- **CRS:** Leaflet `L.CRS.Simple` mapped directly to **EPSG:28350 (MGA Zone 50 metres)**.
- **Units:** Metric $1\text{ unit} = 1\text{ metre}$ ($lat = -\text{northing}$, $lng = \text{easting}$).
- **Precision:** Pins, landmarks, and area polygons are stored in metric coordinates, ensuring that dropped pins remain pinpoint accurate across imagery updates and layer toggles without browser-side projection maths.

### Layers & Map Package (`sitemap/`)
- **Current Aerial Imagery:** L17 high-resolution ($0.25\text{ m/px}$) weekly drone capture of Site C & F (`sitemap/imagery/`).
- **Context Aerial Imagery:** L15 Nearmap site-wide aerial basemap ($1.0\text{ m/px}$).
- **Drawn Site Plan:** Vector-rendered transparent overlay pixel-aligned to the L17 imagery grid, featuring a live opacity slider.
- **Boundaries / Areas:** 169 site boundary/lease polygons loaded from `sitemap/features/*.geojson` via `/api/areas`, providing automatic point-in-polygon assignment when logging pins.
- **Landmarks:** 142 named infrastructure centroids (buildings, tanks, sumps, gates) served via `/api/landmarks`.

### Live Pins & Pin-Drop Workflow
- **Live Open Entries:** Polled every 15 seconds from `/api/open`, color-coded by work type with age-escalation status rings (white → amber → red).
- **Pin-Drop Location Flow:** Interactive pin-drop placement during entry logging with a $\sim 15\text{ m}$ duplicate proximity warning, offering instant catalogue saving or one-off ad-hoc entry pin attachment.

---

## 2. Completed Build Milestones

- **Phase 0–2:** Laravel 13 skeleton, session authentication, role-based access control (Viewer, Logger, Supervisor, Admin), quick log form, open board, close-out flow, searchable history with CSV export, MGA50 Leaflet map, and pin-drop location creation.
- **Phase 3 Catalogue Hygiene:** Supervisor location management UI (`/admin/locations`) supporting filtering, verification of unverified locations, archiving, duplicate location merging with entry migration, and bulk CSV import/export.
- **Work Type Expansion:** Working at Heights job logging support with color coding, board rendering, and dedicated history filters.
- **Terminology Clean-up:** Sanitized all project/client naming to generic "site" terminology and renamed application to **HRWT — High Risk Work Tracker**.

---

## 3. Prioritized Upcoming Tasks

1. **Phase 5 — Control Room Wallboard Mode (`/wallboard`):**
   - High-contrast, full-screen display tailored for wall-mounted control room monitors.
   - Large pin markers, docked open board, and visual flashing alerts for overdue entries (>2h / >4h).

2. **Phase 5 — Shift Handover Report:**
   - Dedicated shift summary view and printable/downloadable handover report listing open, closed, and long-running entries across shift changes.

3. **Phase 5 — Offline-Tolerant Submit Queue:**
   - Client-side submit queue in `localStorage` for field logging on patchy site Wi-Fi/4G with automatic background retry upon connection recovery.

4. **Phase 3 — Interactive Overlay Editor:**
   - In-app drawing tools (e.g. Leaflet-Geoman) allowing supervisors to draw or adjust site boundary polygons and landmark markers directly on the map screen.
