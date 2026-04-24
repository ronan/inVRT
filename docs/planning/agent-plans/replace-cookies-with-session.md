# Replace cookies.json with session.json

## Goal

Switch authenticated session storage from a custom cookies-only JSON
(`cookies.json` + Netscape `cookies.txt`) to Playwright's native
`storageState` format (`session.json`). Use Playwright's
`test.use({ storageState: 'session.json' })` in generated specs and write the
session directly during login. Drop the dead Netscape conversion (wget no
longer crawls).

## Changes

### 1. Config: rename `cookies_file` → `session_file`

- `docs/spec/Application.yaml` — rename key under `Files:`. New default:
  `INVRT_CRAWL_DIR/session.json` (full filename, including extension).
  Description: "Playwright storageState file written by login and consumed
  by tests."
- `docs/spec/config.schema.yaml` — same rename, same default, updated description.
- `docs/spec/config.example.yaml` — update sample.
- Run `task build:templates` to regenerate `src/core/ConfigSchema.php`.

Resulting env var: `INVRT_SESSION_FILE` (full path with `.json`).

### 2. PHP: drop Netscape conversion, rename references

- Delete `src/core/Service/CookieService.php`.
- `src/core/Service/LoginService.php`:
  - Rename parameter `$cookiesFile` → `$sessionFile`.
  - Pass `INVRT_SESSION_FILE` to the Node subprocess (replacing
    `INVRT_COOKIES_FILE`).
  - Remove the `CookieService::convertToNetscapeFormat(...)` call.
  - Update log lines (no `.json` suffix appending — path is already complete).
- `src/core/Runner.php::login()`:
  - Read `INVRT_SESSION_FILE` instead of `INVRT_COOKIES_FILE`.
  - Update log message wording (`session_file=...`).
- `src/core/Configuration.php`: no change required (the `INVRT_COOKIE` raw
  cookie-header default is unrelated and stays).
- `AGENTS.md` and `.github/copilot-instructions.md`: drop `CookieService`
  mentions.

### 3. JS: write storageState directly; consume via `test.use`

- `src/js/playwright-login.js`:
  - Rename env var read to `INVRT_SESSION_FILE`.
  - Replace `context.cookies()` + `JSON.stringify` write with
    `await context.storageState({ path: outputFile })`.
  - Remove the `.json` suffix-appending logic (the env var is the full path).
  - Drop the empty-cookies guard (storageState is always written).
- `src/js/generate-playwright.js`:
  - Read `INVRT_SESSION_FILE` directly (no `.json` suffix concatenation).
  - When the file exists, emit
    `test.use({ storageState: <relPath> });` in the generated spec
    (replacing the dead `use({ storageState: ... })` block, which was
    permanently disabled via `relCookieFile = null`).
  - Compute `relPath` relative to `INVRT_SCRIPTS_DIR` (current behaviour).
- `src/js/playwright-onbefore.js`:
  - Remove the `loadCookies` helper and the `scenario.cookiePath` branch.
    With `storageState` applied at the project/test level, per-page cookie
    injection is unnecessary. Keep the file as a thin pass-through (or its
    debug log) to avoid breaking imports.

### 4. Tests / fixtures

- Rename `tests/fixtures/cookies.json` → `tests/fixtures/session.json` and
  convert content to Playwright `storageState` shape:
  `{ "cookies": [...], "origins": [] }`. Re-grep to verify nothing else
  references the old path.
- Bats tests don't currently exercise the login path, so no test changes
  are required beyond the rename.

### 5. Documentation

- `docs/user/en/usage.md`:
  - Replace the cookies/Netscape paragraph with a one-liner: session is
    saved to `<INVRT_SESSION_FILE>` (default `session.json`) and consumed
    by Playwright via `storageState`.
  - Update the `rm` example commands and the directory-tree listing
    (replace `cookies.json` + `cookies.txt` with `session.json`).
- `docs/user/en/configuration.md`: update the config table row.
- `docs/spec/APP_SUMMARY.md`: drop the Netscape conversion bullet; rename
  the file references.

### 6. Stale planning notes

- Leave historical plan files unchanged (per AGENTS.md: don't read past
  plans). Only update top-level docs.

## Validation

1. `task build:templates` — regenerates `ConfigSchema.php`.
2. `task test` — bats e2e suite must stay green.
3. Manual smoke: in `scratch/theinternet`, run `bin/invrt init <url>` to
   confirm config rendering still works (no auth-required path executed).

## TODO bookkeeping

Move the TODO item from `TODO.md` to `docs/planning/TODO-DONE.md` under
"Authentication" once complete.
