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
1. Run `docker compose up --build` (the `--build` matters the first time, and again any time `Dockerfile` changes — `docker-compose.yml` builds a custom image rather than pulling one).
2. On first start, `config.json`, `state.json`, `slack.json` and `signal.json` are created automatically from their `.example.json` templates, and `config.json`/`state.json` are made writable.
3. Open a web browser and visit `http://localhost:81`.

`config.json`, `state.json`, `slack.json` and `signal.json` hold your actual printer setup, filament inventory, and notification credentials — they're gitignored, so edit them locally without worrying about committing personal data. To reset any of them, delete it and restart the container.

`Dockerfile` builds on `php:apache` but also bakes in `signal-cli` (see below) — it's a normal part of the app container, not something running elsewhere, so `signal.json`'s `cli_path` can reach it directly with no extra setup.

### Manual (without Docker)
1. Open a terminal window.
2. Copy `config.example.json` to `config.json` and adjust it to your printers.
3. Copy `state.example.json` to `state.json`.
4. Grant write access to both files (`chmod 666 config.json state.json`).
5. Start a local PHP server (`php -S localhost:8000`).
6. Open a web browser and visit `http://localhost:8000`.

### Slack notifications (optional)
1. Create a Slack app at [api.slack.com/apps](https://api.slack.com/apps) → **From scratch**.
2. Under **OAuth & Permissions**, add the `chat:write` and `files:write` scopes, then **Install to Workspace** and copy the **Bot User OAuth Token** (`xoxb-...`).
3. Invite the bot to your target channel: `/invite @YourAppName` in that channel (or add the `chat:write.public` scope instead, to skip the invite).
4. Find the channel's ID — right-click the channel in Slack → **View channel details**, it's at the bottom of that panel (looks like `C0123456789`). Channel *names* like `#3d-druck` work for the text message but not for the image attachment, so use the ID.
5. Copy `slack.example.json` to `slack.json` and fill in `bot_token` and `channel` (the ID from step 4).
6. Save a filament color from the app — a message with a small color-swatch image is posted to that channel. `slack.json` is gitignored, and the feature is silently disabled if the file is missing or `bot_token`/`channel` are empty. If the image upload fails for any reason (e.g. `files:write` wasn't granted), it falls back to a plain text message so the notification is never lost.

### Signal notifications (optional)
`signal-cli` is already part of the app's own Docker image (see `Dockerfile`) — nothing extra to install for the Docker path. For the manual (non-Docker) path, install and link [`signal-cli`](https://github.com/AsamK/signal-cli) yourself and make sure it's on `PATH`.

1. Link an account. With Docker, do this through the running app container, so the linked state ends up exactly where `signal.json` will look for it:
   ```
   docker compose exec app signal-cli --config /var/www/html/signal-state link
   ```
   Scan the QR code with the Signal app (**Linked devices → Link new device**). This persists to `./signal-state` on the host (bind-mounted, gitignored — it holds real account keys/session, never commit it). Linking runs as root inside the container, so the files end up owner-only; `docker-entrypoint.sh` loosens `signal-state`'s permissions (`chmod -R a+rwX`) on every container start so Apache's `www-data` worker (not root) can actually read/write them when handling a real save — this matters on a native Linux host like the Pi even if it looks like it works without it on Docker Desktop for Mac, which doesn't enforce bind-mount permissions the same way.
2. Find your target group's id: `docker compose exec app signal-cli --config /var/www/html/signal-state -a +<number> listGroups` — copy the `Id:` value (base64, e.g. `oT+8X2/...`).
3. Copy `signal.example.json` to `signal.json` and fill in `account` (the linked number) and `group_id`. `cli_path` defaults to `"signal-cli --config /var/www/html/signal-state"`, matching where step 1 linked to — for the manual (non-Docker) path, `"signal-cli"` alone is enough if the default `~/.local/share/signal-cli` location already has a linked account.
4. Save a filament color from the app — a message listing what changed is sent to that group. `signal.json` is gitignored, and the feature is silently disabled if the file is missing, `account`/`group_id` are empty, or `cli_path` can't be found/run.

Every attempt (sent or skipped) is logged to `signal.log` next to `index.php` — `tail -f signal.log` while saving a color to see the exact command that ran, its exit code, and its output.

`cli_path` is used as a raw command prefix rather than a single binary path, so it can be any whole command line, not just `signal-cli` directly. Since this only comes from your own local `signal.json`, not from the web UI, it's trusted the same way the rest of that file is.

#### Why signal-cli is baked into the app's own image
It has to live in the same container as the app: PHP's `exec()` runs *inside* the app container, which has no `docker` CLI and no access to the host's Docker daemon, so an earlier `cli_path` of `docker run ... signal-image ...` silently couldn't work once the app itself moved into Docker on a real host — it only ever ran fine in local testing here because that testing invoked PHP directly on the host, not through the containerized app.

`signal-cli` also needs a JRE far newer than Debian (the app's base image) ships, and its official release only bundles the native `libsignal-client` library for `amd64` Linux — not `arm64`, which is what a 64-bit Raspberry Pi runs. `Dockerfile` handles both: a build stage copies a matching JRE from `eclipse-temurin:25-jre`, and for non-`amd64` architectures it downloads a matching prebuilt native lib from [exquo/signal-libs-build](https://github.com/exquo/signal-libs-build) (`arm64`/`armhf` are handled; anything else fails the build with a clear error). If you bump `SIGNAL_CLI_VERSION`, also update `LIBSIGNAL_VERSION` to match — check the `libsignal-client-<version>.jar` filename in that release's `lib/` directory.

#### Standalone signal-cli image (optional)
`Dockerfile.signal-cli`/`signal-cli-docker.sh` build a separate `signal-image`, useful for testing `signal-cli` on its own without the full app stack running. Not needed for normal operation — the app's own image already has everything it needs.

### Per-printer notification overrides
Each printer entry in `config.json` can add `slack`, `signal`, and/or `signal_channel` to override the defaults from `slack.json`/`signal.json` for just that printer:

```json
"printer_2": {
    "name": "Example Printer 2",
    "extruder_count": 5,
    "slack": false,
    "signal": false
},
"printer_3": {
    "name": "Example Printer 3",
    "extruder_count": 1,
    "signal_channel": "another-group-id-base64=="
}
```

* `slack`: set to `false` to skip Slack notifications for that printer's color changes. Omit (or `true`) to use the default (notify).
* `signal`: set to `false` to skip Signal notifications for that printer's color changes. Omit (or `true`) to use the default (notify).
* `signal_channel`: set to a different Signal group id to route that printer's notifications there instead of `signal.json`'s `group_id`. Omit to use the default group.

A save that changes colors on printers with different `signal_channel`s sends one batched Signal message per destination group.