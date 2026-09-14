# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.10.0] - 2026-09-15

### Added
- Signal notification when a filament's color changes, sent to a group via a locally installed `signal-cli` (`signal-cli -a <account> send -g <group_id> -m ...`). Reuses the same batched, change-only detection already built for Slack. Credentials live in a new gitignored `signal.json` (`signal.example.json` is the tracked template), auto-copied by the Docker entrypoint. Silently disabled if the file is missing, `account`/`group_id` are empty, or the `signal-cli` binary can't be found/run — in practice this means it only activates on hosts that actually have `signal-cli` installed and linked.

Verified against a real linked account and group.

### Changed
- Checking the club-membership checkbox now hides and disables the Owner field, since an extruder is either individually owned or belongs to the club, not both. Unchecking restores the previously entered owner name without losing it.

## [0.9.0] - 2026-09-15

### Added
- Configurable club-membership checkbox label (`club_label` in `config.json`), so the "gehört 35services e.V." text can be customized per deployment/workshop. Falls back to that same text if unset, so existing configs are unaffected.

### Changed
- The "Extruder N" heading is hidden for printers with only one extruder, since it's redundant there.
- Updated the README screenshot to reflect the current UI.

## [0.8.0] - 2026-09-15

### Added
- Slack notification when a filament's color changes: posts hex + color name (and printer/extruder) to a configured channel via `chat.postMessage`, using a bot token stored in a new gitignored `slack.json` (`slack.example.json` is the tracked template, auto-copied by the Docker entrypoint like `config.json`/`state.json`).
- Notification only fires for extruders whose hex or color name actually changed compared to the previous save, and multiple changes in one save are batched into a single message.

## [0.7.0] - 2026-09-15

### Added
- "Edit Config" button that opens `config.json` as formatted, editable JSON with a Save button, so the config can be changed directly from the UI instead of editing the file on disk.

### Fixed
- Submitting the state-save form no longer runs when the request is actually a config save (and vice versa) — a latent bug from adding the config editor that could have overwritten `state.json` with an empty object.

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
