# Plan: `invrt info` command

## Problem

There's no quick way to see the current project status — what environments/profiles/devices are configured, how many pages have been crawled, how many screenshots have been captured, and whether the project is ready to test.

## Approach

Add an `invrt info` command that prints a human-readable summary of the current project state. Documentation first, then implementation.

---

## Todos

### 1. Update usage docs (`docs/user/en/usage.md`)

Add `info` to the table of contents and commands section. Show example output.

### 2. Update APP_SUMMARY.md (`docs/developer/en/APP_SUMMARY.md`)

Add `info` command description.

### 3. Expose raw parsed sections from `Configuration`

Add a `getSection(string $key): array` method to `src/core/Configuration.php` that returns a raw parsed YAML section (e.g. `environments`, `profiles`, `devices`, `name`) without resolution. This gives `Runner::info()` access to the full list of configured environments, profiles, and devices.

### 4. Add `info()` method to `Runner`

Add `public function info(): array` to `src/core/Runner.php`. Returns an associative array with:

- `name` — project name from config
- `config_file` — path to config.yaml
- `environment` / `profile` / `device` — current resolved values
- `environments` / `profiles` / `devices` — list of configured names
- `crawled_pages` — line count of `crawled_urls.txt` (0 if absent)
- `captured_screenshots` — count of PNG files under `INVRT_CAPTURE_DIR/bitmaps/` (recursive, 0 if absent)
- `crawl_log_tail` — last 5 lines of `crawl.log` (empty array if absent)

### 5. Create `InfoCommand` (`src/cli/Commands/InfoCommand.php`)

- `#[AsCommand(name: 'info', ...)]`
- Extends `BaseCommand`, `$requiresLogin = false`
- Calls `$this->boot($opts, $io)`, then `$this->runner->info()`
- Renders output using `SymfonyStyle`:
  - Project name + config file path as header
  - Three inline lists: Environments, Profiles, Devices
  - Data rows: Crawled pages, Captured screenshots
  - Crawl log tail (if non-empty) as a block

### 6. Register `InfoCommand` in `src/cli/invrt.php`

Autowire and add to the app loop.

### 7. Write E2E test (`tests/e2e/InfoCommandTest.php`)

Test the happy path:
- Config with environments, profiles, devices
- Command succeeds
- Output contains project name, environment/profile/device names
- Crawled pages shows 0 when no crawl has run

### 8. Move todo item to `docs/planning/TODO-DONE.md`

Remove from `TODO.md`, add under the appropriate section in `TODO-DONE.md`.

---

## Notes

- `invrt info` does not require login (`$requiresLogin = false`)
- Screenshot count scans `bitmaps/reference` and `bitmaps/test` separately and reports both
- The command should not fail if crawl/capture data is absent but should explain the status.
