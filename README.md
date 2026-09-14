# Project Readme

## Summary
This is a management tool for a 3D printer. It requires PHP and a JSON configuration file. No database is necessary.

![](screenshot.png)

## Feature List
* Printer unit and extruder configuration via a JSON file
* State storage in a separate JSON file
* Modification without an authentication requirement
* Color selection via a picker or a hex input field
* Material dropdown using a predefined option
* Ownership assignment via a text input or a club checkbox with a customizable label (`club_label` in `config.json`)
* Input validation to prevent a security exploit
* Optional Slack notification when a filament's color changes
* Optional Signal notification when a filament's color changes, via a locally installed `signal-cli`

## Setup Instruction

### Docker (recommended)
1. Run `docker compose up`.
2. On first start, `config.json`, `state.json`, `slack.json` and `signal.json` are created automatically from their `.example.json` templates, and `config.json`/`state.json` are made writable.
3. Open a web browser and visit `http://localhost:81`.

`config.json`, `state.json`, `slack.json` and `signal.json` hold your actual printer setup, filament inventory, and notification credentials — they're gitignored, so edit them locally without worrying about committing personal data. To reset any of them, delete it and restart the container.

Note: the stock `docker-compose.yml` uses the plain `php:apache` image, which does **not** include `signal-cli`. Signal notifications only work if `signal-cli` is reachable from wherever `index.php` actually runs — either running the app directly on a host that already has `signal-cli` installed and linked (e.g. a Raspberry Pi), or building a custom image that adds it. Without that, the feature just silently does nothing, same as a missing `signal.json`.

### Manual (without Docker)
1. Open a terminal window.
2. Copy `config.example.json` to `config.json` and adjust it to your printers.
3. Copy `state.example.json` to `state.json`.
4. Grant write access to both files (`chmod 666 config.json state.json`).
5. Start a local PHP server (`php -S localhost:8000`).
6. Open a web browser and visit `http://localhost:8000`.

### Slack notifications (optional)
1. Create a Slack app at [api.slack.com/apps](https://api.slack.com/apps) → **From scratch**.
2. Under **OAuth & Permissions**, add the `chat:write` scope, then **Install to Workspace** and copy the **Bot User OAuth Token** (`xoxb-...`).
3. Invite the bot to your target channel: `/invite @YourAppName` in that channel (or add the `chat:write.public` scope instead, to skip the invite).
4. Copy `slack.example.json` to `slack.json` and fill in `bot_token` and `channel` (e.g. `#3d-druck`).
5. Save a filament color from the app — a message is posted to that channel listing what changed. `slack.json` is gitignored, and the feature is silently disabled if the file is missing or `bot_token`/`channel` are empty.

### Signal notifications (optional, requires `signal-cli`)
1. Install and link [`signal-cli`](https://github.com/AsamK/signal-cli) on the machine `index.php` actually runs on, so it's linked to a Signal account (`signal-cli -a +<number> ...`). Only that host can send — see the Docker note above.
2. Find your target group's id with `signal-cli -a +<number> listGroups` — copy the `Id:` value (base64, e.g. `oT+8X2/...`).
3. Copy `signal.example.json` to `signal.json` and fill in `account` (the linked number, e.g. `+491701234567`), `group_id`, and optionally `cli_path` if `signal-cli` isn't on `PATH`.
4. Save a filament color from the app — a message listing what changed is sent to that group via `signal-cli send`. `signal.json` is gitignored, and the feature is silently disabled if the file is missing, `account`/`group_id` are empty, or the `signal-cli` binary can't be found/run.