-- CSE Log schema. Placeholders are substituted per driver by CseLog\Db::migrate():
--   {PK}          auto-increment primary key
--   {FK}          foreign key column type
--   {TABLE_OPTS}  engine/charset (MySQL only)
-- Comments are stripped and statements split on ";" by the migrator.

CREATE TABLE IF NOT EXISTS users (
    id {PK},
    username VARCHAR(80) NOT NULL UNIQUE,
    display_name VARCHAR(120) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'logger',
    active INTEGER NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL
){TABLE_OPTS};

CREATE TABLE IF NOT EXISTS sites (
    id {PK},
    name VARCHAR(120) NOT NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'Australia/Brisbane',
    active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
){TABLE_OPTS};

CREATE TABLE IF NOT EXISTS maps (
    id {PK},
    site_id {FK} NOT NULL,
    name VARCHAR(120) NOT NULL,
    image_path VARCHAR(255) NULL,
    width_px INTEGER NOT NULL DEFAULT 2000,
    height_px INTEGER NOT NULL DEFAULT 1400,
    crs VARCHAR(20) NOT NULL DEFAULT 'simple',
    georeference TEXT NULL,
    is_default INTEGER NOT NULL DEFAULT 0,
    active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
){TABLE_OPTS};

CREATE TABLE IF NOT EXISTS work_types (
    id {PK},
    name VARCHAR(80) NOT NULL UNIQUE,
    colour VARCHAR(16) NOT NULL DEFAULT '#d93a2b',
    is_default INTEGER NOT NULL DEFAULT 0,
    requires_note INTEGER NOT NULL DEFAULT 0,
    sort_order INTEGER NOT NULL DEFAULT 0,
    active INTEGER NOT NULL DEFAULT 1
){TABLE_OPTS};

CREATE TABLE IF NOT EXISTS areas (
    id {PK},
    map_id {FK} NOT NULL,
    name VARCHAR(120) NOT NULL,
    kind VARCHAR(60) NULL,
    geometry_px TEXT NOT NULL,
    colour VARCHAR(16) NOT NULL DEFAULT '#2b7fd9',
    active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
){TABLE_OPTS};

CREATE TABLE IF NOT EXISTS landmarks (
    id {PK},
    map_id {FK} NOT NULL,
    name VARCHAR(120) NOT NULL,
    category VARCHAR(60) NULL,
    x REAL NOT NULL,
    y REAL NOT NULL,
    colour VARCHAR(16) NOT NULL DEFAULT '#1f9d55',
    notes TEXT NULL,
    active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
){TABLE_OPTS};

CREATE TABLE IF NOT EXISTS locations (
    id {PK},
    site_id {FK} NOT NULL,
    map_id {FK} NOT NULL,
    name VARCHAR(160) NOT NULL,
    code VARCHAR(60) NULL,
    area_id {FK} NULL,
    x REAL NOT NULL,
    y REAL NOT NULL,
    lat REAL NULL,
    lng REAL NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    verified INTEGER NOT NULL DEFAULT 0,
    merged_into_id {FK} NULL,
    usage_count INTEGER NOT NULL DEFAULT 0,
    last_used_at DATETIME NULL,
    created_by {FK} NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL
){TABLE_OPTS};

CREATE TABLE IF NOT EXISTS location_aliases (
    id {PK},
    location_id {FK} NOT NULL,
    alias VARCHAR(160) NOT NULL
){TABLE_OPTS};

CREATE TABLE IF NOT EXISTS entries (
    id {PK},
    site_id {FK} NOT NULL,
    map_id {FK} NOT NULL,
    location_id {FK} NULL,
    location_label VARCHAR(160) NOT NULL,
    x REAL NULL,
    y REAL NULL,
    area_id {FK} NULL,
    work_type_id {FK} NOT NULL,
    notes TEXT NULL,
    reported_by VARCHAR(120) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    opened_at DATETIME NOT NULL,
    opened_by {FK} NOT NULL,
    closed_at DATETIME NULL,
    closed_by {FK} NULL,
    close_note TEXT NULL,
    created_at DATETIME NOT NULL
){TABLE_OPTS};

CREATE TABLE IF NOT EXISTS entry_events (
    id {PK},
    entry_id {FK} NOT NULL,
    event VARCHAR(20) NOT NULL,
    actor_id {FK} NULL,
    actor_ip VARCHAR(45) NULL,
    changes TEXT NULL,
    occurred_at DATETIME NOT NULL
){TABLE_OPTS};

CREATE INDEX IF NOT EXISTS ix_entries_status ON entries (site_id, status, opened_at);

CREATE INDEX IF NOT EXISTS ix_entries_opened ON entries (opened_at);

CREATE INDEX IF NOT EXISTS ix_locations_status ON locations (site_id, status);

CREATE INDEX IF NOT EXISTS ix_events_entry ON entry_events (entry_id, occurred_at);
