# Plan: Create `.invrt/plan.yaml` and keep it updated

## Goal

Add first-class support for `plan.yaml` so:

1. `invrt init` creates `.invrt/plan.yaml` with the `project` section initialized from the selected base URL.
2. `invrt check` enriches `.invrt/plan.yaml` with discovered metadata (site title and related check data) and ensures the base URL is represented in `pages` as `/: <title>`.
3. Existing end-user edits in `plan.yaml` are preserved when automatic updates run.

## Required Inputs

- Plan file format source: `docs/planning/proposals/Plan.yaml.specification.md`.
- Existing metadata source from check step: `.invrt/check.yaml` (`INVRT_CHECK_FILE`).

## Scope (this TODO only)

- Add/maintain top-level `project` and `pages` in `.invrt/plan.yaml`.
- Do not implement crawler-driven plan expansion yet.
- Do not implement profile-specific page metadata yet.

## Implementation Steps

1. Documentation-first updates
- Update `docs/user/en/usage.md` to document:
  - when `plan.yaml` is created,
  - what `init` writes,
  - what `check` updates,
  - how user edits are preserved.
- Update `docs/spec/APP_SUMMARY.md` to describe the new behavior at the product/behavior level.

2. Config key for plan file path
- Add `plan_file` to `docs/spec/Application.yaml` defaults, targeting `INVRT_DIRECTORY/plan.yaml`.
- Regenerate `src/core/ConfigSchema.php` using `task build:templates`.

3. Add a dedicated core service for plan document merging
- Create a new static service in `src/core/Service/PlanService.php`.
- Responsibilities:
  - Load existing YAML if present, fallback to empty structure if missing/empty.
  - Ensure root structure exists: `project` (map), `pages` (map).
  - Update only managed keys, preserving all unrelated user-defined keys.
  - Normalize base URL path into plan-root path key (`/` for initial implementation).
  - Write YAML back to `INVRT_PLAN_FILE`.
- Merge policy (preserve user edits):
  - Never delete unknown keys under `project` or `pages`.
  - Only set/overwrite these managed keys:
    - `project.url`
    - `project.title` (when check discovers title)
    - `project.id` (if available from config)
    - `pages['/']` title value (scalar or map title, normalized consistently)

4. Integrate plan creation/update in Runner
- In `Runner::init()`:
  - after `config.yaml` is written, create/update `plan.yaml` with base project metadata.
- In `Runner::check()`:
  - after successful check output is written, parse check YAML and merge discovered metadata into `plan.yaml`.
- Add verbosity-level logger messages for plan create/update actions and failure reasons.

5. Tests (Bats E2E)
- Extend `tests/bats/cli.bats`:
  - `init` creates `.invrt/plan.yaml`.
  - `project.url` is set to initialized URL.
- Extend `tests/bats/workflow.bats`:
  - `check` updates `project.title` and sets/updates `pages./` to homepage title.
  - Existing custom keys in `plan.yaml` remain after `check` update.
- Keep tests behavior-focused; avoid asserting YAML formatting.

6. Validation
- Run `task test` and fix any regressions caused by this feature.

## Notes / Risks

- Symfony YAML dump will not preserve comments/formatting exactly. "Preserve user changes" will be interpreted as preserving data keys/values, not file formatting/comments.
- If homepage title is empty, plan update should still keep structural validity and avoid writing null-ish invalid shapes.

## Acceptance Criteria

- Running `invrt init <url> --skip-baseline` creates `.invrt/plan.yaml` with `project.url` set.
- Running `invrt check` after init enriches `project.title` and ensures homepage exists in `pages` as `/`.
- Pre-existing custom plan fields remain intact after automatic updates.
- Docs and tests are updated and passing.
