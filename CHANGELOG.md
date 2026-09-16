# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.13.1] - 2026-09-16

### Added
- `mock-slack-server.php`, a small fake Slack API for local testing (`chat.postMessage`, `files.getUploadURLExternal`, `files.completeUploadExternal`), so `slack.php`'s real code can be exercised without ever contacting the real Slack workspace. `slack.json` gained an optional `api_base` (defaults to `https://slack.com/api`) so a test copy can point at it.

Verified end-to-end: pointed a scratch copy's `slack.json` at the mock server with a fake bot token, triggered a real save through the app, and confirmed the full upload flow (PNG generation → get-upload-url → upload → complete) ran correctly against the mock with zero real Slack calls.

## [0.13.0] - 2026-09-16

### Changed
- Replaced overloaded `signal_channel: false` with a separate `"signal": false` boolean, symmetric with the existing `"slack": false`. `signal_channel` now only ever holds a group-id override string (or is omitted) — it no longer doubles as an on/off switch.
- `signal-cli-docker.sh` now just runs `docker build -t signal-image .` instead of regenerating `Dockerfile` from a hardcoded heredoc, which had drifted out of sync and was silently reverting the Java-25/arm64-native-lib fix from 0.12.3 every time the script ran.

Verified all combinations (default, `slack:false`, `signal:false`, `signal_channel` override) with a mock `signal-cli` capturing invocation arguments.

### Fixed
- The `signal-cli` Docker image failed to run at all: `UnsupportedClassVersionError` (base image had Java 21, but signal-cli 0.14.8 requires Java 25 — bumped `eclipse-temurin:21-jre` to `25-jre`), then `Missing required native library dependency: libsignal-client` on arm64 (signal-cli's release only bundles that native lib for `amd64` Linux). The `Dockerfile` now detects the build architecture and, for `arm64`/`armhf`, downloads a matching prebuilt native lib from `exquo/signal-libs-build` into the image.

Verified on this arm64 host end-to-end: built the image, ran `listGroups` and a real `send` against the actual linked account through the container, matching exactly how `signal.json`'s `cli_path` would invoke it.

## [0.12.2] - 2026-09-16

### Added
- `Dockerfile` and `signal-cli-docker.sh` to build a `signal-image` container wrapping `signal-cli`, plus README instructions for building it, linking it to a Signal account (`docker run ... signal-image link`, persisting state to `./signal-state`), and pointing `signal.json`'s `cli_path` at the same image/volume for actual sends.

## [0.12.1] - 2026-09-16

### Changed
- `signal.json`'s `cli_path` is now used as a raw command prefix instead of a single escaped binary path, so it can be a whole command line — e.g. `"docker exec my-signal-container signal-cli"` to reach `signal-cli` running in a separate container. Still defaults to `signal-cli` resolved via `PATH`.

Verified with a mock wrapper script capturing its invocation arguments: both the plain single-word default and a multi-word `docker exec`-style prefix produce the correct command.

## [0.12.0] - 2026-09-16

### Added
- Per-printer notification overrides in `config.json`: `"slack": false` skips Slack notifications for that printer, and `"signal_channel"` routes that printer's Signal notifications to a different group (or `false` to skip Signal for it entirely). Omit either to keep using the defaults from `slack.json`/`signal.json`. A save touching printers with different `signal_channel`s sends one batched message per destination group.

Verified all combinations (default, `slack: false`, `signal_channel: false`, `signal_channel` override) end-to-end — Slack via a real send, Signal via a mock `signal-cli` capturing each invocation's arguments to confirm correct grouping/routing without spamming real groups.

### Fixed
- Slack's `files.completeUploadExternal` requires an actual channel ID (`C0123456789`), not a `#channel-name` — that format works for the plain text message but silently failed the image attachment (`invalid_arguments`), always falling back to text-only. `slack.example.json` and the README now document using the channel ID directly, which works for both paths.

Verified with `files:write` granted: the swatch image now attaches successfully end-to-end.

## [0.11.0] - 2026-09-15

### Added
- Slack notifications now attach a small 50×50 PNG swatch per changed color (generated in pure PHP, no GD dependency needed) via Slack's external file upload flow, with the message text as the attachment's caption. Falls back to the existing plain-text message if the upload fails for any reason (e.g. the bot token is missing the `files:write` scope), so notifications are never lost over an image issue.

### Changed
- Moved the Slack and Signal notification code out of `index.php` into their own `slack.php`/`signal.php` files, each self-loading its own config (`slack_load_config()`/`signal_load_config()`) and exposing a single `notify_*_color_changes($changes)` entry point. `index.php` just requires both and calls them.

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
