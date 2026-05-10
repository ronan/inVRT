# Port the CLI from PHP/Symfony to TypeScript

## Problem

The current inVRT CLI is implemented in PHP with Symfony Console and Composer-managed tooling, while the rest of the runtime is already Node-based. The requested change is to replace the PHP/Symfony CLI with a TypeScript CLI built on Commander, Chalk, Zod, Conf, and Execa, remove PHP code and PHP tooling from the repository, and keep the Bats end-to-end suite passing.

## Proposed approach

Replace the PHP boot/config/runner layer with a TypeScript CLI entrypoint and supporting modules that preserve the current command surface and filesystem behavior expected by the Bats suite. Reuse the existing Node/Playwright/Astro workflow where it still fits, but move configuration resolution, plan discovery, command orchestration, logging, prompting, and subprocess execution into TypeScript so the CLI can run without PHP or Composer.

## Todos

1. Audit the current command contract and test expectations.
   - Capture every command, option, prompt, exit behavior, output phrase, and artifact path that the Bats tests currently depend on.
   - Identify all PHP-owned behavior that must move into TypeScript.

2. Build the new TypeScript CLI foundation.
   - Add a TypeScript runtime/build setup for the executable in `bin/invrt`.
   - Implement Commander command registration for `init`, `check`, `crawl`, `reference`, `test`, `approve`, `baseline`, `config`, `info`, `report`, `configure-playwright`, and `generate-playwright`.
   - Add shared option parsing, help/version output, chalk-based console formatting, zod-based input/config validation, execa-based subprocess execution, and conf-backed local persistence only where it meaningfully replaces existing PHP state handling.

3. Port configuration loading and runtime environment export.
   - Recreate plan file discovery precedence and config merging behavior in TypeScript.
   - Preserve the resolved `INVRT_*` environment contract consumed by the existing JS scripts.
   - Replace PHP YAML parsing/writing and project-id generation with Node-based equivalents.

4. Port command orchestration from `src/core` into TypeScript services.
   - Re-implement init/check/crawl/reference/test/approve/baseline/config/info/report behavior to match current semantics.
   - Preserve first-run flows such as prompting for a URL, auto-init, auto-crawl, auto-reference, and plan enrichment.
   - Keep verbose/debug logging behavior compatible with the current `-vvv` Bats assertions.

5. Remove PHP runtime code and tooling.
   - Delete PHP source files and Composer metadata once the TypeScript replacement is wired in.
   - Remove Composer-based tasks and PHP-specific lint/tooling from the Dockerfile, Taskfile, docs, and tests.
   - Replace any remaining PHP usage in helpers or scripts with Node/TypeScript equivalents.

6. Update tests and fixtures for a PHP-free runtime.
   - Replace the Bats YAML helper that shells out to PHP with a Node-based helper.
   - Replace the PHP built-in fixture web server used by Bats with a non-PHP server while preserving the existing test behavior.
   - Adjust any tests only where the migration intentionally changes surfaced behavior, otherwise keep expectations intact.

7. Update documentation to reflect the TypeScript CLI before implementation is considered complete.
   - Update `docs/user/en/usage.md` for the new CLI/tooling expectations.
   - Update `docs/developer/en/APP_SUMMARY.md` to describe the new behavior and architecture without implementation details.
   - Refresh root/developer docs that currently describe Symfony, PHP, or Composer requirements.

8. Validate the migration end-to-end.
   - Run the repo test command(s) needed after the tooling switch.
   - Fix remaining incompatibilities until the Bats suite passes under the TypeScript CLI.

## Notes and considerations

- The Bats suite currently depends on PHP in two places outside the CLI itself: YAML reads in `test_helper.bash` and the fixture web server. Both must be replaced to truly remove PHP tooling.
- The tests assert specific phrases and artifact locations, so the TypeScript port should preserve user-visible behavior unless a deliberate change is necessary.
- The current JS/Playwright/Astro scripts already form most of the runtime. The migration should avoid rewriting stable behavior unnecessarily and instead replace the PHP orchestration layer cleanly.
- The Taskfile and install flow will need a Node-only equivalent for repository setup and validation after Composer is removed.
