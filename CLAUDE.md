# CLAUDE.md

Project-specific instructions for Claude Code working in this repo.

## Testing rules (important)

- **Never send real Slack messages during development or testing.** Don't call the live Slack API (`chat.postMessage`, `files.getUploadURLExternal`, etc.) against the real bot token/channel in `slack.json`, even for "just a quick check" — it posts to a real workspace channel with real people watching it. If a change needs verifying, describe what you'd test and ask the user first; let them decide whether to authorize a real send.
- **Never send real Signal messages during development or testing**, for the same reason — `signal.json`'s account is the user's real personal Signal identity, and `group_id` points at real group chats (family, community, work). Verify `signal.php`/`signal-cli` logic with a mock `signal-cli` instead:
  ```bash
  # bin/signal-cli — logs the call instead of sending
  #!/bin/bash
  echo "CALL: $*" >> /tmp/mock-signal-cli.log
  exit 0
  ```
  then run the test server with that directory prepended to `PATH` (`PATH="$scratch/bin:$PATH" php -S ...`) and inspect `/tmp/mock-signal-cli.log` for the exact command that would have been run — account, group id, and message text.
- If real verification is ever genuinely needed, ask the user first. Don't treat "they didn't say no" as permission.

## Deployment topology

- There are (at least) two independent live deployments of this app:
  1. **This Mac**, a local Docker container (`simple-filament-tool-app-1`, `localhost:81`) bind-mounted to this exact project directory. Editing files here takes effect immediately — no rebuild needed for PHP/config changes.
  2. **`octo35services.tail19e18b.ts.net:81`**, a separate physical host (likely the actual Raspberry Pi) on the Tailscale network. It is **not** the same filesystem — it has its own `config.json`/`state.json`/`slack.json`/`signal.json` and does **not** auto-update from git. Pushing to `origin/main` does not deploy there; it needs its own `git pull` plus its own copies of the gitignored data/secrets files.
- `config.json`, `state.json`, `slack.json`, `signal.json` are gitignored — real per-deployment data and credentials, never committed. The `*.example.json` files are the tracked templates/schema reference. `docker-entrypoint.sh` copies each `.example.json` to the real filename **only if it doesn't already exist**, so updating an example file never retroactively changes an already-deployed instance — existing deployments need a manual config update to pick up new fields.

## Testing workflow

- Never modify the real project's `config.json`/`state.json` directly to test a save flow. Copy the relevant files into the scratchpad directory, run an isolated `php -S localhost:<port>` there, test against that copy, then clean up (kill the server, `rm -rf` the scratch dir). Confirm the real files are untouched afterward before reporting a task done.
- This Mac's Docker runs `arm64` natively (Apple Silicon) — the same architecture as a 64-bit Raspberry Pi. Local Docker testing here is representative of real Pi behavior for architecture-sensitive issues (e.g. native library availability), which is how the `signal-cli` Java-version and missing-`arm64`-native-lib bugs were caught before ever reaching the Pi.

## Release process

- Bump `VERSION` and add a dated entry to `CHANGELOG.md` (Keep a Changelog format) for every user-facing change, however small.
- In this project, once the user has asked for something to be pushed, they've consistently wanted each subsequent verified change committed and pushed right away without being asked again each time — keep that rhythm unless told otherwise.
