# Plan: Extend the use of plan.yaml

Implements the "Extend the use of plan.yaml" task from TODO.md. Goal: make
plan.yaml the single source of truth for project metadata, the exclude list,
configured profiles, and the page tree — eliminating `exclude-paths.txt` and
the intermediate `check.yaml`.

## Target plan.yaml shape

```yaml
project:
  url: https://example.test
  id: abcd
  name: my-project          # from config.yaml project.name (init)
  title: Home               # from <title> on home page (check)
  checked_at: 2026-04-24T…  # set by check
profiles:                    # configured profiles from config.yaml (check)
  - anonymous
  - editor
exclude:                     # default + user-edited (init seeds defaults)
  - /logout
  - /user/logout
  - /files
  - /download
  - /assets
  - /images
pages:
  /:
    profiles: [anonymous]
    id: xyz
  /about.html: …
```

Notes:
- `project.https` is dropped (derivable from URL).
- `project.redirected_from` is dropped (was only in check.yaml; not requested).
- `check.yaml` and `INVRT_CHECK_FILE` are removed entirely. `check.js` writes
  the metadata it gathered straight to plan.yaml via stdout JSON consumed by
  PHP, or by writing plan.yaml directly. Decision: keep the script writing its
  output to stdout (simple, testable) and have `Runner::check()` merge it into
  plan.yaml using `PlanService::update()`. The intermediate file is dropped —
  Runner reads stdout via `node->runCapturing()`.
- `exclude-paths.txt` and `INVRT_EXCLUDE_FILE` are removed. `crawl.js` reads
  `exclude` from plan.yaml; if absent, falls back to a small in-code default
  (`['/user/*']`) for safety.

## Changes

### 1. `docs/spec/Application.yaml`

- Remove `Files.exclude_file` block.
- Remove `Files.check_file` block.
- Update the `check` command's `help` text (no longer mentions check.yaml).

### 2. Regenerate `src/core/ConfigSchema.php`

- Run `task build:templates` after editing the spec. Removes
  `exclude_file` and `check_file` from `DEFAULTS` and the tree builder.
- Verify `tooling/templates/ConfigSchema.tpl.php` doesn't need changes
  (it iterates over the spec).

### 3. `src/core/Service/PlanService.php`

Extend `update()` to accept and merge:
- `name` (string) → `project.name`
- `checkedAt` (string ISO8601) → `project.checked_at`
- `profiles` (string[]) → top-level `profiles` (replace, not merge — reflects
  current config)
- `excludePaths` (string[]) → top-level `exclude` (only set when current value
  is missing/empty, so user edits aren't overwritten)

Old positional signature gets messy. Switch to a single `array $data` param
shape:

```php
PlanService::update(string $planFile, array $data): bool
```

Where `$data` recognises keys: `url`, `id`, `name`, `title`, `home_title`,
`checked_at`, `profiles`, `exclude`. Each is applied only when non-empty,
mirroring current behaviour.

Drop `home_title` writing to `pages./` only when title is supplied.

### 4. `src/core/Runner.php`

- `init()`:
  - Drop `$excludeFile = …` and the writeFile call for exclude-paths.txt.
  - Drop the `excludeFile` env var read (will not exist after schema regen).
  - Pass `name` (from cwd basename, same as config), `exclude` (default list),
    and `profiles` (`array_keys($this->config->getSection('profiles') ?? [])`)
    to `PlanService::update()` so plan.yaml is initialised with full project
    metadata up-front.
- `check()`:
  - Replace `runNode('check.js', null, $outputFile)` with
    `node->runCapturing('check.js')`, parse stdout YAML.
  - Drop `INVRT_CHECK_FILE` references and the check.yaml warn-when-missing
    branch.
  - Pass `title`, `home_title`, `checked_at`, `profiles` to
    `PlanService::update()`. Profiles list comes from
    `$this->config->getSection('profiles')`.
  - `https` and `redirected_from` are not written.

### 5. `src/js/check.js`

- Drop `https` from the output object (still computed for the log line).
- Drop `redirected_from` from output (no longer needed by Runner).
- Keep `url`, `title`, `checked_at` on stdout.

### 6. `src/js/crawl.js`

- Drop import of `INVRT_EXCLUDE_FILE`.
- Replace `resolveExcludeMatchers()` to read `exclude` from plan.yaml; default
  to `['/user/*']` if missing/empty.

### 7. `src/cli/Commands/CheckCommand.php`

- Update `help:` attribute text to remove the "written to .invrt/data/check.yaml"
  reference. Replace with "merged into .invrt/plan.yaml".

### 8. Tests (`tests/bats/`)

- `cli.bats` — "init: writes selected url environment profile and device":
  - Remove `assert_file_exists "$TEST_DIR/.invrt/exclude-paths.txt"`.
  - Add assertions for new plan.yaml keys: `project.name`, `exclude.0`,
    `profiles.0`.
- `workflow.bats` — "check: writes check yaml with site metadata":
  - Drop `check.yaml` assertions; replace with plan.yaml assertions for
    `project.title`, `project.checked_at`, `project.url`.
  - Drop `https: false` and `redirected_from` assertions.
  - Rename test to "check: enriches plan.yaml with site metadata".
- `workflow.bats` — "check: preserves user-defined plan keys": already merges
  via PlanService — verify still passes.
- `tests/bats/test_helper.bash` and `tests/fixtures/config*.yaml`: no changes
  expected (already use config.yaml profiles section).

### 9. Docs

- `docs/user/en/usage.md` — remove `.invrt/exclude-paths.txt` mention; replace
  with "Edit `exclude` in `.invrt/plan.yaml` to skip paths."
- `docs/developer/en/APP_SUMMARY.md` — update if it mentions exclude file or
  check.yaml. (Verify during implementation.)

### 10. Cleanup leftovers

- Search for any other references to `INVRT_EXCLUDE_FILE`, `INVRT_CHECK_FILE`,
  `exclude-paths`, `check.yaml`, `exclude_file`, `check_file` outside
  `docs/planning/` (historical) and remove.
- Old planning docs under `docs/planning/agent-plans/` are historical — leave
  alone per AGENTS.md ("Do not read past plans when implementing new plans").

## Validation

1. `task build:templates` — regenerates ConfigSchema cleanly.
2. `task test` — bats suite green; PHP CS Fixer clean.
3. Manual smoke: `cd scratch/theinternet && rm -rf .invrt && bin/invrt init https://example.test --skip-baseline && cat .invrt/plan.yaml` — confirm shape matches target.

## Out of scope

- "Remove backstop.js as a requirement" (separate TODO bullet).
- Replace cookies.json with session.json (separate Authentication TODO).
- Backstop config still reads plan.yaml (no change needed).
