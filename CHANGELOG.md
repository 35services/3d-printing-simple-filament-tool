# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.6.0] - 2026-09-15

### Added
- `VERSION` file and a version footer in the UI, so a deployed instance can be checked against the expected release.
- This changelog.

## [0.5.0] - 2026-09-15

### Added
- Hex color codes for every entry in `color_list`, sourced from Prusament product thumbnails (dominant-color extraction) and hand-supplied values for the German color names.
- Product thumbnail images (`image` field) for the Prusament colors, hotlinked from Prusa's CDN.
- Selecting a preset color now also fills the hex field/color picker and shows the product thumbnail; both are saved per extruder in `state.json`.
- Thumbnails back-fill by color name for entries saved before this feature existed.

## [0.4.1] - 2026-09-14

### Fixed
- `config.json` is now made writable (`chmod 666`) alongside `state.json`, both on manual setup and by the Docker entrypoint. Previously only `state.json` was writable, so editing `config.json` directly failed with a permission error.

## [0.4.0] - 2026-09-14

### Changed
- `config.json` and `state.json` hold per-deployment data and are no longer tracked in git (now gitignored). `config.example.json` and `state.example.json` are committed instead as templates.
- The Docker entrypoint copies the example files into place on first run and fixes `state.json` permissions, so `docker compose up` works without manual setup steps.

## [0.3.0] - 2026-09-14

### Added
- Configurable filament color list (`color_list` in `config.json`) with a dropdown to pick a preset color name; the manual text field remains for custom entries.

### Changed
- Redesigned the UI: card layout for printers/extruders, labeled fields, styled Save button. Inline CSS only, no external stylesheets or fonts.

## [0.2.0] - 2026-09-14

### Added
- Docker Compose setup (`docker-compose.yml`) for running the app via `php:apache`.

## [0.1.0] - 2026-09-09

### Added
- Initial release: printer/extruder configuration via `config.json`, state storage in `state.json`, color selection via a picker or hex input, material dropdown, owner text/club checkbox, and input validation.
