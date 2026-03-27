# Plan: Refactor src/invrt-reference.sh and src/invrt-test.sh

## Problem

`src/invrt-reference.sh` and `src/invrt-test.sh` are thin wrappers — each just echoes a status line and calls a node script. There's no reason for the bash layer to exist. Both have a `# TODO: move this to the php runner app.` comment.

## Approach

Remove both bash scripts entirely. Override `execute()` in `ReferenceCommand` and `TestCommand` to:
1. Emit the status line via `$output->writeln()`
2. Call node directly via PHP `passthru()`

Follows the same pattern as `CrawlCommand`, which already overrides `execute()` and handles everything in PHP.

## Steps

1. **Override `execute()` in `ReferenceCommand`**
   - Init env via `EnvironmentService`, handle login (same as BaseCommand flow)
   - Emit `📸 Capturing references...` via `$output->writeln()` at `VERBOSITY_VERBOSE`
   - Call `node {INVRT_SCRIPTS_DIR}/backstop.js reference` via `passthru()`
   - Return exit code
   - Return `''` from `getScriptName()` (like `ConfigCommand`)

2. **Override `execute()` in `TestCommand`**
   - Same pattern — emit `🔬 Testing...` then call `node .../backstop.js test`

3. **Delete `src/invrt-reference.sh`**

4. **Delete `src/invrt-test.sh`**

5. **Run tests / update as needed**

## Files Changed

- `src/Commands/ReferenceCommand.php` — override `execute()`, `getScriptName()` returns `''`
- `src/Commands/TestCommand.php` — override `execute()`, `getScriptName()` returns `''`
- `src/invrt-reference.sh` — deleted
- `src/invrt-test.sh` — deleted
- `tests/E2E/` — verify existing tests pass; update if needed
- `TODO.md` — mark tech debt items done
