# Plan: User Scripting

## Goal

Implement page-level user scripts defined in `.invrt/plan.yaml` so Playwright specs can run user-provided code at `before`, `ready`, and `after` for a page, with inheritance from parent paths and support for either script files or inline code.

## Current State

- `src/js/generate-playwright.js` already parses the `pages` tree from `plan.yaml` and emits one Playwright test per resolved path.
- The generator currently ignores page metadata other than whether a node is testable.
- `Runner::init()` creates `.invrt/scripts/` and an empty `onready.js`, but the scaffold is not described in docs and is not wired into generated specs.
- Existing Bats coverage already validates `generate-playwright`, `init`, and preservation of `ready` metadata in `plan.yaml`.

## Target Behavior

- A page node in `plan.yaml` may define `before`, `ready`, and `after` values.
- Script values may be:
  - a `.js` or `.ts` file path,
  - a bare script filename resolved relative to `INVRT_SCRIPTS_DIR`,
  - inline code when the value does not end in `.js` or `.ts`.
- Script definitions on a parent page apply to all descendant pages unless overridden on a child page.
- `generate-playwright` emits the resolved user code into the generated spec so `playwright test` executes it at the correct phase.
- `init` creates a root-level ready hook script scaffold in `.invrt/scripts/` with a one-line comment explaining its purpose.

## Implementation Steps

1. Documentation-first updates
- Update `docs/user/en/usage.md` to document page hook keys, inheritance, file-vs-inline behavior, and the init scaffold.
- Update `spec/APP_SUMMARY.md` to describe how `generate-playwright` resolves and executes per-page scripts.

2. Extend page traversal in `src/js/generate-playwright.js`
- Replace the current path-only extraction with a traversal that returns page entries containing:
  - the resolved URL path,
  - the effective `before`, `ready`, and `after` hooks after inheriting parent values,
  - any existing page id metadata when useful for stable test generation.
- Keep current path semantics for `/`, child paths, and query children.
- Keep `INVRT_MAX_PAGES` limiting behavior unchanged.

3. Add script resolution helpers in `src/js/generate-playwright.js`
- Resolve hook values by event name.
- Treat values ending in `.js` or `.ts` as script files.
- If the file value is not absolute and not already rooted under `.invrt`, resolve it from `INVRT_SCRIPTS_DIR`.
- Read file-backed scripts from disk and embed their contents into the generated spec.
- Treat any other string as inline code and embed it directly.
- Fail clearly when a referenced script file does not exist.

4. Emit hooks in generated Playwright tests
- Generate helper wrappers in the spec so user code runs with the Playwright `page` object available.
- Execute hooks in this order per test:
  - `before` before `page.goto(...)`
  - `ready` after `page.goto(..., { waitUntil: 'networkidle' })` and before the screenshot assertion
  - `after` after the screenshot step in a `finally` block so cleanup still runs when the test body fails
- Keep snapshot naming and deterministic test IDs unchanged.

5. Update init scaffolding in `src/core/Runner.php`
- Replace the empty default script with an `onready.ts` scaffold in `.invrt/scripts/`.
- Write a single-line comment describing that the file runs after a page is ready and before the screenshot is captured.
- Keep init behavior otherwise unchanged.

6. Tests
- Extend the `generate-playwright` Bats test to seed a `plan.yaml` with:
  - a root hook,
  - a child override,
  - one inline script,
  - one file-backed script.
- Assert the generated spec contains the injected user code and that inherited hooks appear on descendant tests.
- Extend the `init` Bats test to assert `.invrt/scripts/onready.ts` exists and contains the scaffold comment.
- Add one failure-path test that references a missing script file and confirms `generate-playwright` exits non-zero with a clear error.

7. TODO tracking
- Move the completed User Scripting items from `TODO.md` to `docs/planning/TODO-DONE.md` under the appropriate section.

8. Validation
- Run the narrow Bats tests for `generate-playwright` and `init` first.
- Run `task test` after the focused checks pass.

## Local Hypothesis

The missing behavior is controlled entirely by `src/js/generate-playwright.js` and `Runner::init()`: once the generator computes inherited hook metadata and emits it into each test, Playwright will run user scripts without any new PHP orchestration layer.

## Cheap Disconfirming Check

Seed a parent page with `onready: onready.ts` and a child page without its own hook, run `invrt generate-playwright`, and verify the child test in the generated spec contains the root script code. If it does not, inheritance is not being computed in the generator and the hypothesis is wrong or incomplete.

## Acceptance Criteria

- `plan.yaml` can define `before`, `ready`, and `after` on any page node.
- Parent page hooks apply to descendants unless overridden.
- File-backed `.js` and `.ts` hooks and inline hook bodies are both supported.
- Generated Playwright specs execute hooks in the expected order.
- `init` scaffolds `.invrt/scripts/onready.ts` with a descriptive comment.
- Focused Bats tests and `task test` pass.
