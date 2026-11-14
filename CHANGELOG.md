# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-11-14

### Added
- **Authentication & Role-Based Access Control**:
  - Roles: `Admin`, `Supervisor`, `Logger`, and `Viewer`.
  - Admin user management console (`/admin/users`) for creating and managing operator accounts.
- **Quick Logging & Location Tracking**:
  - Rapid radio call entry logging with customizable work types (default Confined Space Entry, Hot Work, etc.).
  - Search-as-you-type location catalogue with alias support.
  - Interactive pin-drop on site map with one-click saving to catalogue.
- **Open Board**:
  - Real-time tracking board with elapsed-time badges and amber (>2h) / red (>4h) threshold warnings.
  - One-click close-out with confirmation and close notes.
- **Live Site Map**:
  - Interactive Leaflet viewer supporting MGA Zone 50 coordinates.
  - Georeferenced site plan overlay with Esri World Imagery satellite basemap.
  - Live pin rendering with age-coded status badges and popup details.
- **Audit Logging & History**:
  - Filterable entry history (by date range, work type, status, and search query).
  - One-click CSV export (`/history.csv`) and immutable event log.
- **Diagnostics & Operations**:
  - Centralized server and client-side error logging (`/error`) with downloadable diagnostic dumps.
  - Automated database migration, seeders, and `sitemap:import` artisan command.
  - Deployment configuration for cPanel Git Version Control (`.cpanel.yml`).
  - GitHub Actions CI workflow with PHPUnit and Playwright visual acceptance tests.
  - Multi-stage Dockerfile and Docker Compose environment for local and containerized deployments.
