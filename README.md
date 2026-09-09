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
1. Open a terminal window.
2. Create a new directory.
3. Save `index.php` into the directory.
4. Save `config.json` into the directory.
5. Create an empty `state.json` file.
6. Grant write access to the state file (`chmod 666 state.json`).
7. Start a local PHP server (`php -S localhost:8000`).
8. Open a web browser and visit `http://localhost:8000`.