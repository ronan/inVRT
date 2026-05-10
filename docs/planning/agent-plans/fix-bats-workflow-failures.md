# Plan: fix Bats workflow failures

## Problem

`task test:bats` is failing in the workflow suite for three separate reasons:

1. `build-plan-tree.js` throws `Invalid URL` when crawl output contains relative paths like `/about.html`.
2. `PlaywrightRunner` launches Playwright in a way that breaks module resolution for generated specs that import `@playwright/test`.
3. `report` logs success even when `generate-report.js` fails, and the report generator assumes a fully populated `report.json`.

## Approach

1. Update the crawl tree builder to parse app paths without requiring absolute URLs.
2. Run Playwright through the repository-installed CLI with stable Node module resolution from generated config/spec directories.
3. Make report generation fail loudly on subprocess errors and handle minimal report fixtures safely enough for the existing Bats coverage.
4. Re-run `task test` to confirm the workflow suite passes without regressing the rest of the project checks.

## Todos

1. Fix `src/js/build-plan-tree.js` path parsing and keep existing page tree behavior intact.
2. Fix `src/core/Service/PlaywrightRunner.php` so `reference`, `test`, and `approve` execute against the local Playwright installation reliably.
3. Fix `src/js/generate-report.js` and `src/core/Runner.php` report handling so failures propagate and minimal fixtures still render.
4. Run `task test` and inspect any remaining failures.

## Notes

- Keep the fixes surgical; these are bug fixes, not behavior redesigns.
- Preserve current command output where possible so the existing Bats assertions stay valid.
