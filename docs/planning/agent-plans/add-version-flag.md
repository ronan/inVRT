# Plan: Add `--version` flag support

## Context
`--version` already works through Symfony Console's global option handling in the app bootstrap.

## Goal
Ensure `invrt --version` is explicitly supported and protected against regressions.

## Short implementation plan
1. Add an e2e test that executes `invrt --version` and asserts success.
2. Assert output includes CLI name and current semantic version value.
3. Optionally add one line to usage docs mentioning `--version` global option.
4. Run focused test and `task test` to confirm no regressions.

## Acceptance checks
- `invrt --version` exits with code 0.
- Output includes the current app version.
- Test suite remains green.
