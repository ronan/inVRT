# Plan: Remove `passthru()` — Use Symfony Process Component

## Problem

`passthru()` is called in 4 places and writes directly to PHP's raw stdout, bypassing Symfony's output system entirely. This means output never goes through `OutputInterface` — `CommandTester` can't capture it, and commands can't control verbosity or formatting.

The TODO item is:
> Remove use of 'passthru' in php for testibility

## Root Cause

`passthru()` writes to stdout. The fix lives in the **production code**: route subprocess output through `$output->write()` so it flows through Symfony's output system, which `CommandTester` captures natively.

## Scope — All passthru() call sites

| File | Method | Runs |
|---|---|---|
| `BaseCommand::executeScript()` | `passthru($envStr . $cmd)` | Bash scripts (e.g. `invrt-crawl.sh`) |
| `LoginService::loginIfCredentialsExist()` | `passthru("$env node playwright-login.js")` | Node login script |
| `ReferenceCommand::execute()` | `passthru("node backstop.js reference")` | Node backstop reference |
| `TestCommand::execute()` | `passthru("node backstop.js test")` | Node backstop test |

## Approach

Add `symfony/process` as a dependency. Replace every `passthru()` call with `Symfony\Component\Process\Process`, streaming output through `$output->write()` via a callback. This:

1. Routes all subprocess output through `OutputInterface` — testable via `CommandTester->getDisplay()`
2. Preserves real-time streaming (same UX as `passthru`)
3. Is idiomatic in a Symfony Console app
4. Requires no test-side workarounds

### Pattern

Before:
```php
passthru($cmd, $exitCode);
```

After:
```php
$process = Process::fromShellCommandline($cmd);
$process->setTimeout(null);
$process->run(fn($type, $buffer) => $output->write($buffer));
$exitCode = $process->getExitCode() ?? Command::SUCCESS;
```

### BaseCommand::executeScript()

`$output` is not currently in scope here. Add it as a parameter:

```php
protected function executeScript(string $scriptName, array $env, OutputInterface $output): int
```

Update the one call site in `BaseCommand::execute()` to pass `$output`.

## Files Changed

| File | Change |
|---|---|
| `composer.json` / `composer.lock` | Add `symfony/process` |
| `src/Commands/BaseCommand.php` | `executeScript()` gains `OutputInterface $output`; uses `Process` |
| `src/Service/LoginService.php` | Replace `passthru()` with `Process`; `$output` already in scope |
| `src/Commands/ReferenceCommand.php` | Replace `passthru()` with `Process` |
| `src/Commands/TestCommand.php` | Replace `passthru()` with `Process` |
| `TODO.md` | Mark tech debt item done |

## Order of Work

1. `composer require symfony/process`
2. Update `BaseCommand::executeScript()` — add `$output`, use `Process`
3. Update `LoginService` — use `Process`
4. Update `ReferenceCommand` — use `Process`
5. Update `TestCommand` — use `Process`
6. Run `task test` — all green
7. Update `TODO.md`
