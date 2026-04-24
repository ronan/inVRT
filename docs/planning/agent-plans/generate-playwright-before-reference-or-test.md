# Plan: Generate Playwright Spec Before Reference or Test

## Goal

Ensure inVRT regenerates the Playwright spec from `plan.yaml` before both `reference` and `test` runs.

## Current Behavior

- `reference()` validates crawl output and calls `generatePlaywright()` before running Playwright.
- `test()` only triggers `reference()` when reference screenshots are missing.
- If references already exist, `test()` runs with the previously generated spec and may not reflect recent `plan.yaml` changes.

## Desired Behavior

- `reference()` keeps current behavior: always regenerate spec first.
- `test()` also regenerates spec from `plan.yaml` before running Playwright, even when references already exist.

## Changes

1. Update `src/core/Runner.php`
- In `Runner::test()`, call `generatePlaywright()` before `runPlaywright('test', $env)`.
- Keep first-run behavior unchanged (`test()` should still call `reference()` when references are missing).
- Preserve current logging and exit-code semantics.

2. Add Bats coverage in `tests/bats/workflow.bats`
- Add a test proving `test` regenerates spec from `plan.yaml` when references already exist.
- Suggested flow:
  - seed config + crawl
  - run `reference` once (creates initial spec)
  - change `.invrt/plan.yaml` to a different page set
  - run `test`
  - assert generated `desktop.spec.ts` reflects updated plan paths

3. Update docs
- Update `docs/user/en/usage.md` (`test` section): mention spec generation from `plan.yaml` occurs before test execution.
- Update `docs/spec/APP_SUMMARY.md` (`test` section): document that `test` regenerates Playwright spec from `INVRT_PLAN_FILE` before run.

4. TODO tracking
- Move the completed TODO item from `TODO.md` to `docs/planning/TODO-DONE.md` under `Features` → `User Scripting`.

5. Validation
- Run focused Bats test(s) for the new behavior.
- Run `task test`.

## Acceptance Criteria

- Running `invrt test` always uses a freshly generated Playwright spec from current `plan.yaml`.
- Existing first-run auto-reference behavior remains intact.
- Tests and docs reflect the updated behavior.
