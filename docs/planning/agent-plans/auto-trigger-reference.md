# Plan: Auto Trigger Reference When Test is Run for First Time

## Problem

When `invrt test` is run without a prior `invrt reference`, BackstopJS fails with a missing
reference directory error. New users have to know to run reference first. Instead, the tool
should detect the missing references and run the reference step automatically.

## Approach

In `TestCommand`, after the environment is resolved, check whether the reference bitmaps
directory exists and contains at least one PNG. If not, run `runBackstop('reference', ...)` first
with an informational message, then proceed with the test as normal.

The reference directory path is: `{INVRT_DATA_DIR}/bitmaps/reference`

## Steps

1. **Update `docs/usage.md`** — document the auto-reference behavior under the `test` section
   (mark as FUTURE FEATURE until implemented and tested).

2. **Update `TestCommand.php`** — after env resolution, check for reference PNGs.
   If none found, log a notice and call `runBackstop('reference', ...)` first.
   If reference step fails, return early with that exit code.

3. **Update `tests/e2e/TestCommandTest.php`** — add a test case that runs `invrt test`
   without first running `invrt reference`, and asserts the command succeeds and reference
   bitmaps were created.

4. **Remove FUTURE FEATURE tag** from usage docs once tests pass.

5. **Check off the todo item** in `TODO.md`.
