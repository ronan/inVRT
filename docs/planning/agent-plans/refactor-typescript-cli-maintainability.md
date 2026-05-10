# Refactor the TypeScript CLI for readability and maintainability

## Problem

The TypeScript CLI works and the Bats suite passes, but the new `src/ts/` layer still carries a fair amount of migration-shaped code. There is repeated command wiring in `cli.ts`, repeated process-running patterns across `process.ts` and `playwright.ts`, stringly-typed boot/init error handling, and repeated runtime context formatting in `runner.ts`. Those patterns make the code harder to scan, harder to change safely, and easier to drift as the CLI grows.

## Proposed approach

Refactor the TypeScript CLI without changing behavior. Focus on extracting small shared helpers, reducing repeated command/action boilerplate, replacing fragile string error sentinels with explicit types, and consolidating subprocess execution paths so the runtime has fewer places to maintain output handling and command invocation logic.

## Refactor candidates

1. Consolidate CLI command registration in `src/ts/cli.ts`.
   - Extract a reusable helper for selection-based commands (`config`, `info`, `check`, `approve`, `crawl`, `reference`, `test`, `baseline`, `report`, hidden commands).
   - Reduce repeated `selectionSchema.parse(...)`, `boot(...)`, `bootOrInitialize(...)`, and `process.exitCode = ...` patterns.
   - Keep command names and behavior exactly the same.

2. Replace string-based control-flow errors with typed errors/results.
   - Introduce explicit error classes or result objects for boot/init/prompt failures instead of checking magic strings like `'missing config'` and `'missing url'`.
   - Keep terminal output and exit codes unchanged.

3. Centralize subprocess execution behavior.
   - Extract the shared execa/output handling logic currently split between `process.ts` and `playwright.ts`.
   - Keep Playwright-specific argument construction separate, but reuse a common process runner for execution, output capture, result file writing, and line routing.

4. Reduce repeated runtime context formatting in `runner.ts`.
   - Extract helpers for reading the active target context (`url`, `profile`, `device`, `environment`) and formatting the repeated status messages for `reference`, `test`, and `approve`.
   - Remove small repetition like repeated config lookups and duplicated success/failure branches where possible.

5. Simplify plan/config mutation helpers.
   - Extract repeated record/list guards and section merge logic in `plan.ts` and `configuration.ts` into smaller helpers.
   - Remove unused imports or dead result fields introduced during the migration.
   - Preserve current plan file shapes expected by the Bats suite.

## Constraints

- Do not change command names, options, prompts, output phrases, exit codes, or filesystem layout.
- Keep `task test` green after the refactor.
- Prefer smaller helper extractions over introducing a large abstraction layer.

## Validation

- Run `task test`.
- Confirm no user-visible command behavior changed.
