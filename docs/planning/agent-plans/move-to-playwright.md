# Plan: Move to Playwright

## Overview

Replace BackstopJS with Playwright for screenshot capture and visual regression testing. Two sub-tasks:

1. `configure-playwright`: copy the bundled `tooling/config/playwright.config.ts` to `INVRT_PLAYWRIGHT_CONFIG_FILE` before generating the Playwright spec.
2. `reference` and `test` run `npx playwright test` instead of BackstopJS.

---

## Changes

### New: `Runner::configurePlaywright()`

Copy the contents of `<appDir>/../../tooling/config/playwright.config.ts` to `INVRT_PLAYWRIGHT_CONFIG_FILE`. Create the directory if needed. Return 0 on success, 1 on failure. Hardcode the content into the command. Do not reference the original file in code.

### Update: `Runner::generatePlaywright()`

Call `$this->configurePlaywright()` before generating the spec. If it fails, return early.

### New: `Runner::runPlaywright(string $mode, array $env): int`

Private method. Runs `npx playwright test --config=<INVRT_PLAYWRIGHT_CONFIG_FILE>`.

- mode `reference`: appends `--update-snapshots`
- mode `test`: no extra flags

Pipes stdout as notice-level log lines, stderr as error-level. Calls `writeResultsFile()` with the captured output.

### Update: `Runner::reference()`

Replace `ensureBackstopConfig()` + `runBackstop('reference', …)` with `generatePlaywright()` + `runPlaywright('reference', …)`.

### Update: `Runner::test()`

Replace `runBackstop('test', …)` with `runPlaywright('test', …)`.

### Update: `Runner::approve()`

Replace `runBackstop('approve', …)` with `runPlaywright('reference', …)` (Playwright approve = re-run with --update-snapshots).

### Update: `Runner::baseline()`

Replace `configureBackstop()` + `runBackstop()` calls with `generatePlaywright()` + `runPlaywright()`. Remove the explicit directory-preparation calls and the trailing `approve()` since the reference run already writes snapshots.

### New: `ConfigurePlaywrightCommand`

Hidden Symfony Console command (`configure-playwright`). Extends `BaseCommand`, `$requiresLogin = false`. Calls `$this->runner->configurePlaywright()`.

### Register in `invrt.php`

Add `ConfigurePlaywrightCommand` to the container and app command list.

---

## No schema changes needed

`INVRT_PLAYWRIGHT_CONFIG_FILE` already exists in `ConfigSchema::DEFAULTS`.

---

## Tests

- Unit test `Runner::configurePlaywright()` verifies the file is copied to the expected path.
- Existing Playwright e2e test passes.
