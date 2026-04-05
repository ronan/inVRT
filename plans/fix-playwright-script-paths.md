# Plan: Fix Playwright hook script paths

## Todo item
- The path of `playwright-onbefore.js` and `playwright-onload.js` are incorrect.
- Current behavior looks in the user-scripts directory, but these scripts live in app source code.

## Goal
Load built-in Playwright hook scripts from the inVRT source directory by default, while preserving user script behavior where intended.

## Short implementation plan
1. Trace where Playwright hook paths are resolved and identify all call sites that reference `onbefore`/`onload` hooks.
2. Correct path resolution so built-in hooks resolve from `src/` (or the canonical app script location) instead of user script directory.
3. Align naming and references (`onload` vs `onready`) so command/runtime behavior matches actual script files.
4. Add/adjust tests to verify path resolution and hook execution for the default built-in scripts.
5. Run `task test` and confirm no regressions.

## Acceptance checks
- Hook scripts are found and executed from the expected app source location.
- No missing-file errors for default hook scripts.
- Existing command flows still pass with `task test`.
