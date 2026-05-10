# Port the remaining JS runtime helpers to TypeScript

## Problem

The CLI layer is now TypeScript, but the operational runtime in `src/js/` is still plain JavaScript. That leaves the crawl, check, Playwright generation, login, reporting, and shared plan helpers outside the typechecked code path. The result is mixed conventions, duplicated helper logic between `src/ts/` and `src/js/`, and reduced editor/runtime safety in the part of the application that still does most of the work.

## Proposed approach

Port the remaining runtime source files in `src/js/` to TypeScript, compile them to emitted JavaScript for execution, and update the TypeScript runner to execute the compiled runtime helpers instead of source `.js` files. Keep behavior, output, environment variables, filesystem layout, and Bats expectations unchanged.

## Scope

### Files to port

- `src/js/check.js`
- `src/js/crawl.js`
- `src/js/build-plan-tree.js`
- `src/js/configure-playwright.js`
- `src/js/generate-playwright.js`
- `src/js/generate-report.js`
- `src/js/info.js`
- `src/js/playwright-login.js`
- `src/js/logger.js`
- `src/js/lib/plan.js`
- `src/js/lib/encode-id.js`
- `src/js/lib/stdio.js`

### Files to review during port

- `src/js/playwright-onbefore.js`
- `src/js/playwright-onready.js`
- `src/js/report.sqrl`

These may remain non-TypeScript assets if they are runtime snippets/templates rather than executable program modules.

## Planned changes

1. Create a dedicated TypeScript runtime source/build path for the helper scripts.
   - Move executable helper modules from `src/js/` to a TypeScript source location while preserving their current module boundaries.
   - Add a build step that emits runnable JavaScript for those helpers.
   - Keep the report template and any non-code assets in place unless a move is clearly beneficial.

2. Update runtime invocation paths.
   - Change the TypeScript runner so `runNodeScript(...)` executes the emitted helper output rather than the source `.js` files.
   - Preserve current script names and CLI behavior.

3. Port shared helper modules first.
   - Type `logger`, `plan`, `encode-id`, and `stdio` so the entrypoints can reuse typed helper functions instead of ad hoc runtime checks.
   - Prefer extracting shared types where that reduces duplication between runtime helpers.

4. Port each runtime entrypoint with behavior parity.
   - `check`, `crawl`, `build-plan-tree`, `generate-playwright`, `generate-report`, `info`, `configure-playwright`, and `playwright-login`.
   - Preserve stdout/stderr contracts and existing pino log behavior because the CLI runner and Bats tests depend on them.

5. Remove redundant JavaScript source files once the TypeScript runtime is wired in.
   - Delete the old `.js` source modules that were ported.
   - Keep generated emitted JavaScript output only where it is part of the runtime/build artifact path.

6. Clean up duplication revealed by the port.
   - Reuse or extract common helpers where the JS-to-TS port exposes repeated parsing or tree-walking logic.
   - Avoid changing public behavior while reducing low-value boilerplate.

7. Validate the migration end-to-end.
   - Run `task test`.
   - Confirm that the runtime helpers still work through the real CLI and Bats suite.

## Assumed implementation approach

The plan assumes the runtime helpers will be emitted to a dedicated build output directory and executed from there, rather than using a TypeScript runtime loader in production. That keeps the shipped runtime plain Node-executable JavaScript and matches the current CLI build approach.

## Constraints

- Do not change command names, options, prompt text, output phrases, exit codes, or artifact paths.
- Do not change the behavior of the Playwright/report runtime unless required for the TypeScript port.
- Keep `task test` green.
