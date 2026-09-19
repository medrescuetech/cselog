# 03 — Data model

MySQL 8 / MariaDB. DDL below is a sketch to argue about, not final migrations.

## Key decisions

1. **An entry never depends on the catalogue.** It carries its own pin coordinates. If a
   location is later renamed, moved, archived or merged, the history still renders exactly where
   it was logged.
2. **Positions are versioned.** `location_positions` keeps `effective_from`, so correcting a
   pin doesn't rewrite the past.
3. **Both coordinate systems on every point.** `x`,`y` (image pixels) always; `lat`,`lng`
   whenever the map has a georeference. Add georeferencing later and backfill — nothing is
   stranded.
4. **Soft states, no hard deletes,** anywhere history can point: locations, work types, maps.
5. **GeoJSON for overlays.** Landmarks and areas are small; storing them as JSON keeps the
   editor round-trip trivial and avoids needing spatial indexes.

## Tables

```sql
-- ── Sites & maps ─────────────────────────────────────────────────────────────
CREATE TABLE sites (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name          VARCHAR(120) NOT NULL,
  timezone      VARCHAR(64)  NOT NULL DEFAULT 'Australia/Brisbane',
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NULL, updated_at TIMESTAMP NULL
);

CREATE TABLE maps (                      -- a named map surface for a site
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  site_id       BIGINT UNSIGNED NOT NULL,
  name          VARCHAR(120) NOT NULL,   -- "Survey plan 2026", "Level 2 plant"
  is_default    TINYINT(1) NOT NULL DEFAULT 0,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (site_id) REFERENCES sites(id)
);

CREATE TABLE map_versions (              -- the actual raster, versioned
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  map_id          BIGINT UNSIGNED NOT NULL,
  label           VARCHAR(120),          -- "rev C, Mar 2026"
  image_path      VARCHAR(255),          -- single image (small maps)
  tile_url_tpl    VARCHAR(255),          -- /maps/7/tiles/{z}/{x}/{y}.png (large maps)
  width_px        INT UNSIGNED NOT NULL,
  height_px       INT UNSIGNED NOT NULL,
  min_zoom        TINYINT, max_zoom TINYINT,
  crs             ENUM('simple','epsg3857') NOT NULL DEFAULT 'simple',
  georeference    JSON NULL,             -- affine transform + control points, see 04-
  is_current      TINYINT(1) NOT NULL DEFAULT 1,
  uploaded_by     BIGINT UNSIGNED, created_at TIMESTAMP NULL,
  FOREIGN KEY (map_id) REFERENCES maps(id)
);

-- ── Overlays: landmarks & boundaries/areas ───────────────────────────────────
CREATE TABLE landmarks (
  id        BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  map_id    BIGINT UNSIGNED NOT NULL,
  name      VARCHAR(120) NOT NULL,
  category  VARCHAR(60),                 -- gate, muster, tank, shaft, hydrant…
  icon      VARCHAR(60), colour VARCHAR(16),
  x DOUBLE NULL, y DOUBLE NULL, lat DOUBLE NULL, lng DOUBLE NULL,
  notes     TEXT, active TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (map_id) REFERENCES maps(id)
);

CREATE TABLE areas (                     -- boundaries / zones
  id        BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  map_id    BIGINT UNSIGNED NOT NULL,
  name      VARCHAR(120) NOT NULL,
  kind      VARCHAR(60),                 -- zone, exclusion, contractor-lot, boundary
  geometry_px   JSON NOT NULL,           -- GeoJSON Polygon/MultiPolygon in pixel space
  geometry_geo  JSON NULL,               -- same, in lat/lng, when georeferenced
  colour    VARCHAR(16), fill_opacity DECIMAL(3,2) DEFAULT 0.20,
  sort_order INT DEFAULT 0, active TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (map_id) REFERENCES maps(id)
);

-- ── Location catalogue ───────────────────────────────────────────────────────
CREATE TABLE locations (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  site_id       BIGINT UNSIGNED NOT NULL,
  map_id        BIGINT UNSIGNED NOT NULL,
  name          VARCHAR(160) NOT NULL,   -- "Sump 4, north wall"
  code          VARCHAR(60)  NULL,       -- asset/permit reference if one exists
  area_id       BIGINT UNSIGNED NULL,    -- cached containing area
  status        ENUM('active','archived') NOT NULL DEFAULT 'active',
  verified      TINYINT(1) NOT NULL DEFAULT 0,   -- promoted-from-pin starts unverified
  merged_into_id BIGINT UNSIGNED NULL,   -- duplicate folded into another location
  usage_count   INT UNSIGNED NOT NULL DEFAULT 0,
  last_used_at  TIMESTAMP NULL,
  created_by    BIGINT UNSIGNED, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_loc_name (site_id, name),
  FULLTEXT KEY ft_loc (name, code),
  FOREIGN KEY (site_id) REFERENCES sites(id),
  FOREIGN KEY (map_id)  REFERENCES maps(id)
);

CREATE TABLE location_aliases (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  location_id BIGINT UNSIGNED NOT NULL,
  alias VARCHAR(160) NOT NULL,
  UNIQUE KEY uq_alias (location_id, alias),
  FOREIGN KEY (location_id) REFERENCES locations(id)
);

CREATE TABLE location_positions (        -- versioned pin position
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  location_id BIGINT UNSIGNED NOT NULL,
  map_version_id BIGINT UNSIGNED NOT NULL,
  x DOUBLE NOT NULL, y DOUBLE NOT NULL,
  lat DOUBLE NULL, lng DOUBLE NULL,
  effective_from TIMESTAMP NOT NULL,
  moved_by BIGINT UNSIGNED, reason VARCHAR(255),
  FOREIGN KEY (location_id) REFERENCES locations(id)
);

-- ── Work types (customisable picklist) ───────────────────────────────────────
CREATE TABLE work_types (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL UNIQUE,      -- "Confined Space Entry"
  colour VARCHAR(16) DEFAULT '#d9534f',
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  requires_note TINYINT(1) NOT NULL DEFAULT 0,   -- e.g. "Other" must be described
  sort_order INT DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1
);

-- ── Entries (the log) ────────────────────────────────────────────────────────
CREATE TABLE entries (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  site_id       BIGINT UNSIGNED NOT NULL,
  map_id        BIGINT UNSIGNED NOT NULL,
  map_version_id BIGINT UNSIGNED NOT NULL,
  location_id   BIGINT UNSIGNED NULL,    -- null = ad-hoc, "don't save this location"
  location_label VARCHAR(160) NOT NULL,  -- snapshot of the name as logged
  x DOUBLE NULL, y DOUBLE NULL,          -- pin as logged (always stored)
  lat DOUBLE NULL, lng DOUBLE NULL,
  area_id       BIGINT UNSIGNED NULL,    -- containing boundary at time of logging
  work_type_id  BIGINT UNSIGNED NOT NULL,
  notes         TEXT,
  reported_by   VARCHAR(120),            -- who radioed in (free text or crew list)
  status        ENUM('open','closed','cancelled') NOT NULL DEFAULT 'open',
  opened_at     DATETIME NOT NULL,       -- UTC, auto = now()
  opened_by     BIGINT UNSIGNED NOT NULL,
  closed_at     DATETIME NULL,
  closed_by     BIGINT UNSIGNED NULL,
  close_note    TEXT,
  KEY ix_open (site_id, status, opened_at),
  KEY ix_hist (site_id, opened_at),
  FOREIGN KEY (work_type_id) REFERENCES work_types(id)
);

CREATE TABLE entry_events (              -- immutable audit trail
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  entry_id BIGINT UNSIGNED NOT NULL,
  event ENUM('created','updated','closed','reopened','cancelled') NOT NULL,
  actor_id BIGINT UNSIGNED, actor_ip VARCHAR(45),
  changes JSON, occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_entry (entry_id, occurred_at),
  FOREIGN KEY (entry_id) REFERENCES entries(id)
);

-- users / roles: Laravel default users table + a `role` column
-- (viewer | logger | supervisor | admin)
```

## Derived / computed

- **Elapsed time** = `now() - opened_at` for open entries; never stored.
- **Area label** computed on write by point-in-polygon against `areas`, cached in
  `entries.area_id` (so a later boundary change doesn't silently rewrite history).
- **`usage_count` / `last_used_at`** bumped on each entry, and used to rank the picker.

## Seed data

`work_types`: Confined Space Entry (default), Hot Work, Working at Heights, Excavation,
Electrical Isolation, Inspection, Other (`requires_note = 1`).
