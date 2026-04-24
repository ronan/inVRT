# Plan: Remove BackstopJS as a requirement

## Context

Reference / test / approve already run via Playwright (`PlaywrightRunner`).
The remaining BackstopJS surface area is dead/redundant code, the
`configure-backstop` command, the `backstop_config_file` config var, the
`backstop-config.js` and `backstop.js` Node scripts, the `backstopjs` npm
dependency, and the doc/spec/test references to all of the above.

## Changes

### Spec / generated config

- `docs/spec/Application.yaml`
  - Remove `Files.backstop_config_file`.
  - Remove the `configure-backstop` command entry.
  - Remove `backstop_config_file` from any command `output_fields` /
    references.
  - Drop mentions of BackstopJS from prose.
- `docs/spec/APP_SUMMARY.md` and `docs/spec/config.schema.yaml`
  - Re-generate after running `task build:templates --force`. If they are
    hand-edited, scrub backstop references manually.
- `src/core/ConfigSchema.php`
  - Regenerated from the spec; no manual edit.

### PHP

- Delete `src/cli/Commands/ConfigureBackstopCommand.php`.
- Remove `ConfigureBackstopCommand` registration from `src/cli/invrt.php`
  and `bin/invrt`.
- Delete `Runner::configureBackstop()` from `src/core/Runner.php`.
- Remove the `backstop.js / playwright-login.js` comment hint in
  `src/cli/Commands/BaseCommand.php` (replace with playwright-only).
- Update `BaselineCommand` and `ApproveCommand` help text to drop
  BackstopJS references.

### Node

- Delete `src/js/backstop.js`.
- Delete `src/js/backstop-config.js`.

### Tests

- `tests/bats/workflow.bats`
  - Delete the test
    `crawl: scenario labels in backstop config are short lowercase ids`.
- Run `task test` and confirm 32/32 pass.

### Docs

- `docs/user/en/usage.md`
  - Replace the "BackstopJS comparison report" mention in the data layout
    with the Playwright HTML report path.
  - Remove any other backstop wording.

### Dependencies

- `package.json`
  - Remove `backstopjs` from dependencies.
  - Run `npm install` (or `npm prune`) to refresh the lockfile.

## Verification

- `task test` passes.
- `grep -ri backstop src/ docs/ tests/ bin/ package.json` returns no
  matches (other than possibly historical entries in
  `docs/planning/TODO-DONE.md`, which we leave alone).
- `bin/invrt list` no longer shows `configure-backstop`.

## Out of scope

- Reorganising the `.invrt/data/<profile>/...` layout. The Playwright
  output dirs already in use stay as they are.
- Cleaning up the old `bitmaps/` legacy paths under data dirs (none are
  currently produced).
