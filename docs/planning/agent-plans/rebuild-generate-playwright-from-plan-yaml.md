# Plan: Rebuild `generate-playwright` to use `plan.yaml`

## Goal

Make `invrt generate-playwright` read page definitions from `.invrt/plan.yaml` instead of `crawled-paths.text`, while preserving existing command flow and output location.

## Current State

- `Runner::generatePlaywright()` pipes `INVRT_CRAWL_FILE` into `src/js/generate-playwright.js`.
- `generate-playwright.js` reads stdin lines as URL paths.
- Bats test `generate-playwright: generates spec from crawled urls` seeds `crawled-paths.text` and validates generated spec/config files.

## Target Behavior

- `generate-playwright.js` should derive test pages from `INVRT_PLAN_FILE` (`.invrt/plan.yaml`).
- Page extraction should support current flat page keys in `pages` (e.g. `/`, `/about.html`) and ignore non-path metadata keys.
- Keep current generated spec shape and snapshot assertions.
- Keep `INVRT_MAX_PAGES` limit behavior.
- Keep deterministic ID generation behavior for test names.

## Implementation Steps

1. Documentation-first updates
- Update `docs/user/en/usage.md` to state `generate-playwright` uses `plan.yaml` pages.
- Update `docs/spec/APP_SUMMARY.md` command behavior from `INVRT_CRAWL_FILE` input to `INVRT_PLAN_FILE` input.

2. Runner input wiring
- Update `Runner::generatePlaywright()` to pass `INVRT_PLAN_FILE` as input to `runNode('generate-playwright.js', ...)`.

3. JS generator rewrite
- In `src/js/generate-playwright.js`:
  - Parse YAML from stdin (`js-yaml`) rather than newline paths.
  - Read `pages` object and collect keys that start with `/`.
  - Sort and limit by `INVRT_MAX_PAGES`.
  - Build Playwright tests from those paths as before.
  - Fail with clear error if no valid page paths are present.

4. Tests
- Update Bats test name and fixture setup:
  - Replace crawl-file seeding with a seeded `.invrt/plan.yaml` containing at least `/` and `/about.html`.
  - Assert generated spec includes tests from plan paths.
- Keep existing assertions for file outputs and basic spec content.

5. TODO tracking
- Move completed item from `TODO.md` to `docs/planning/TODO-DONE.md` under the appropriate section.

6. Validation
- Run `task test` and fix regressions.

## Acceptance Criteria

- `invrt generate-playwright` succeeds when `plan.yaml` has valid page paths.
- Generated spec no longer depends on `crawled-paths.text`.
- Existing workflows remain green (`task test` passes).
