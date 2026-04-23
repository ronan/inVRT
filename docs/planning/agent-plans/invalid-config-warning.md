# Plan: Invalid Config is a Warning, Not a Fatal Error

## Goal

If the config YAML loads successfully but fails schema validation (e.g. has unexpected keys), show a user-friendly warning and continue — rather than aborting with a fatal error.

## Current Behavior

`YamlLoader::load()` calls Symfony's `Processor::processConfiguration()` which throws `InvalidConfigurationException` on schema violations. This exception propagates to `Configuration::__construct()`, then to `BaseCommand::boot()`, where it is caught and returns `Command::FAILURE`.

## Desired Behavior

1. Schema validation failure → warning stored on `Configuration`, execution continues with the raw parsed YAML as fallback.
2. `boot()` displays the warning via `$io->warning()` but continues.
3. Execution only stops if `url` is missing/empty (existing logic handles that downstream).

---

## Changes

### 1. `YamlLoader::fromFile()` — return shape change

Change `fromFile()` to return `['data' => array, 'warning' => ?string]` instead of `array`.

In `load()`, catch `InvalidConfigurationException`:
- On failure: return the raw `$loaded` array (skipping schema defaults/normalisation) and set `warning` to a friendly message including the exception message.
- On success: return the processed array with `warning => null`.

### 2. `Configuration::__construct()` — store warnings

- Change `$this->parsed = YamlLoader::fromFile(...)` to destructure the new return shape.
- Store any warning in `private array $warnings = []`.
- Add `public function getWarnings(): array` method.

### 3. `BaseCommand::boot()` — surface warnings, don't fail

- After constructing `Configuration`, call `$config->getWarnings()`.
- If non-empty, call `$io->warning(implode("\n", $config->getWarnings()))`.
- Do **not** return `Command::FAILURE` — continue as before.
- Keep the existing `catch (\Exception $e)` for genuine parse failures (YAML syntax errors, file read errors).

---

## Files Changed

- `src/core/YamlLoader.php`
- `src/core/Configuration.php`
- `src/cli/Commands/BaseCommand.php`

## Tests

Add a unit test in `tests/Unit/ConfigurationTest.php`:
- Config with an unexpected key → `getWarnings()` returns a non-empty array, and `get('INVRT_URL')` still resolves correctly from the raw data.

Add a bats test in `tests/bats/cli.bats`:
- Running a command with an invalid config (unexpected key) → exits 0, output contains a warning message.
