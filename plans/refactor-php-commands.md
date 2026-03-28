# Refactor PHP Command Codebase

## Problem

There is repeated code across the command classes in `src/Commands/`:

1. **`boot()` boilerplate** — every command that extends `BaseCommand` contains the same 3-line guard:
   ```php
   $result = $this->boot($opts, $io);
   if (\is_int($result)) { return $result; }
   $env = $result;
   ```
   Affects: `CrawlCommand`, `ReferenceCommand`, `TestCommand`, `ConfigCommand`.

2. **`ReferenceCommand` and `TestCommand` are near-duplicates** — they differ only in the backstop mode string (`'reference'`/`'test'`) and one log message. The same inline `Process` block is repeated verbatim.

3. **`joinPath()` is duplicated** in `BaseCommand` and `InitCommand`. `InitCommand` can't extend `BaseCommand` (it has no environment dependency), so it needs its own copy.

## Approach

### 1. Add `withEnv()` to `BaseCommand`

Replace the 3-line boot guard with a single callback-based helper:

```php
protected function withEnv(
    InvrtInput $opts,
    SymfonyStyle $io,
    callable $callback,
    bool $requiresConfig = true,
): int {
    $result = $this->boot($opts, $io, $requiresConfig);
    return \is_int($result) ? $result : $callback($result);
}
```

Each command's `__invoke()` becomes:
```php
return $this->withEnv($opts, $io, function(array $env) use ($io): int {
    // ... work
});
```

`ConfigCommand` passes `requiresConfig: false`:
```php
return $this->withEnv($opts, $io, fn($env) => ..., requiresConfig: false);
```

### 2. Extract `runBackstop()` into `BaseCommand`

Move the shared backstop execution logic out of `ReferenceCommand`/`TestCommand`:

```php
protected function runBackstop(string $mode, array $env, SymfonyStyle $io): int
{
    $process = Process::fromShellCommandline(
        'node ' . escapeshellarg($env['INVRT_SCRIPTS_DIR'] . '/backstop.js') . ' ' . $mode,
        null,
        $env,
    );
    $process->setTimeout(null);
    $process->run(fn($type, $buffer) => $io->write($buffer));
    return $process->getExitCode() ?? Command::SUCCESS;
}
```

`ReferenceCommand` and `TestCommand` become thin one-liners.

### 3. Extract `joinPath()` to a `PathHelper` trait

Create `src/Support/PathHelper.php`:

```php
trait PathHelper
{
    protected function joinPath(string ...$segments): string
    {
        return implode(DIRECTORY_SEPARATOR, $segments);
    }
}
```

- `BaseCommand` uses `PathHelper` (removes the method from the class body)
- `InitCommand` uses `PathHelper` (replaces its private duplicate)

## Files to Change

| File | Change |
|------|--------|
| `src/Support/PathHelper.php` | **Create** — new trait |
| `src/Commands/BaseCommand.php` | Add `withEnv()`, add `runBackstop()`, use `PathHelper` trait |
| `src/Commands/ReferenceCommand.php` | Use `withEnv()` + `runBackstop()` |
| `src/Commands/TestCommand.php` | Use `withEnv()` + `runBackstop()` |
| `src/Commands/CrawlCommand.php` | Use `withEnv()` |
| `src/Commands/ConfigCommand.php` | Use `withEnv()` |
| `src/Commands/InitCommand.php` | Use `PathHelper` trait |

## Validation

- Run `task test` — all existing tests must pass unchanged
- Run `task baseline:phpstan` only if intentional type changes are made

## Completion

- Mark `[ ] Refactor the php command codebase` as `[x]` in `TODO.md`
