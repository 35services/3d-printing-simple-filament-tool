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
* Ownership assignment via a text input or a club checkbox
* Input validation to prevent a security exploit

## Setup Instruction

### Docker (recommended)
1. Run `docker compose up`.
2. On first start, `config.json` and `state.json` are created automatically from `config.example.json` and `state.example.json`, and `state.json` is made writable.
3. Open a web browser and visit `http://localhost:81`.

`config.json` and `state.json` hold your actual printer setup and filament inventory — they're gitignored, so edit them locally without worrying about committing personal data. To reset either one, delete it and restart the container.

### Manual (without Docker)
1. Open a terminal window.
2. Copy `config.example.json` to `config.json` and adjust it to your printers.
3. Copy `state.example.json` to `state.json`.
4. Grant write access to the state file (`chmod 666 state.json`).
5. Start a local PHP server (`php -S localhost:8000`).
6. Open a web browser and visit `http://localhost:8000`.