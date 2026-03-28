# Plan: Replace `joinPath` with `symfony/filesystem`

## Problem

The project has a custom `joinPath` function (`implode(DIRECTORY_SEPARATOR, $segments)`) defined in two places:
- `src/Support/PathHelper.php` — a trait used by commands
- `src/Service/EnvironmentService.php` — duplicated as a private method

`symfony/filesystem` provides `Path::join()` which does the same thing, more robustly.

## Approach

Add `symfony/filesystem` as a dependency and replace all 13 `joinPath` calls with `Path::join()`, then delete the now-unused custom implementations.

## Steps

1. **Add dependency** — `composer require symfony/filesystem:^8.0`

2. **Update `src/Service/EnvironmentService.php`**
   - Add `use Symfony\Component\Filesystem\Path;`
   - Replace all 6 `$this->joinPath(...)` calls with `Path::join(...)`
   - Remove the private `joinPath()` method

3. **Update `src/Commands/BaseCommand.php`**
   - Add `use Symfony\Component\Filesystem\Path;`
   - Replace 1 `$this->joinPath(...)` call with `Path::join(...)`

4. **Update `src/Commands/InitCommand.php`**
   - Add `use Symfony\Component\Filesystem\Path;`
   - Replace 5 `$this->joinPath(...)` calls with `Path::join(...)`

5. **Update `src/Commands/ConfigCommand.php`**
   - Add `use Symfony\Component\Filesystem\Path;`
   - Replace 1 `$this->joinPath(...)` call with `Path::join(...)`

6. **Delete `src/Support/PathHelper.php`** — the trait is no longer needed

7. **Remove `PathHelper` use** from any classes that still reference it (check all command files)

8. **Run `task test`** — confirm all tests pass and linting is clean

9. **Check off todo** in `TODO.md`
