# Plan: Move `configure-backstop` into a new command

## Goal

Expose `configure-backstop` as a standalone Symfony Console command while keeping `reference` auto-triggering it when needed.

## Changes

### 1. `src/core/Runner.php`
- Add public `configureBackstop(): int` method that runs `backstop-config.js` unconditionally (always regenerates).
- Update private `ensureBackstopConfig()` to call `$this->configureBackstop()` internally, keeping the "skip if exists" guard in `ensureBackstopConfig()`.

### 2. `src/cli/Commands/ConfigureBackstopCommand.php` (new file)
- `#[AsCommand(name: 'configure-backstop', ..., hidden: true)]` — hidden since it's marked `internal` in the spec.
- `$requiresLogin = false`
- `boot($opts, $io)` with `requiresConfig: true` (config must exist; crawl output is required input).
- Calls `$this->runner->configureBackstop()`.

### 3. `src/cli/invrt.php`
- Add `use App\Commands\ConfigureBackstopCommand;`
- Autowire and register `ConfigureBackstopCommand`.

### 4. `TODO.md`
- Move the two completed items to `docs/planning/TODO-DONE.md`.

## Non-changes
- `reference()` continues to call `ensureBackstopConfig()` (auto-trigger preserved).
- No new tests needed beyond running `task test` — the command is thin glue code.
