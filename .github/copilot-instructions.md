# Copilot Instructions for inVRT

## What This Project Is

inVRT is a **Symfony Console CLI application** (not Laravel) for running Visual Regression Testing (VRT) against websites (Drupal, Backdrop, etc.). PHP orchestrates configuration; bash scripts handle crawling (wget); Node.js tools (Playwright, BackstopJS) handle browser automation and screenshot comparison.

## Commands

```bash
# Testing
task test                   # Run full PHPUnit suite (--testdox output)
task test:unit              # Unit tests only
task test:e2e               # E2E tests only
task test:coverage          # Coverage report (text)

# Run a single test file
vendor/bin/phpunit tests/Unit/CookieServiceTest.php

# Run a single test method
vendor/bin/phpunit --filter testMethodName tests/Unit/CookieServiceTest.php

# Static analysis
task analyze                # PHPStan level 5
task analyze:baseline       # Regenerate phpstan-baseline.neon
```

## Architecture

```
Symfony Console Commands (src/Commands/)
         ↓
  Service Layer (src/Service/)
         ↓
  Bash scripts (src/*.sh)  ←→  Node.js (src/*.js, Playwright, BackstopJS)
```

All commands extend `BaseCommand`, which initialises `EnvironmentService`, optionally invokes `LoginService`, then calls `passthru()` on a bash script. Each command returns a bash script name via the abstract `getScriptName(): string` method.

**Configuration merging** in `EnvironmentService` applies overrides in this priority order (highest to lowest):

1. `environments.<name>` block
2. `profiles.<name>` block
3. `devices.<name>` block
4. Base config

Resolved values are exported as `putenv()` environment variables so bash and Node scripts can read them.

## Key Conventions

### Namespaces & File Layout
- `App\Commands\` → `src/Commands/`
- `App\Service\` → `src/Service/`
- `Tests\Unit\` → `tests/Unit/`
- `Tests\E2E\` → `tests/E2E/`
- `Tests\Fixtures\` → `tests/fixtures/`

### Adding a New Command
1. Extend `BaseCommand`
2. Set `$defaultName` and `$defaultDescription`
3. Implement `getScriptName(): string` returning the corresponding bash script filename
4. Call `parent::configure()` then add any extra options in `configure()`
5. Use `$this->environment->getEnvironmentArray()` for config values in `execute()`

### Static Service Classes
`LoginService` and `CookieService` are entirely static — no instantiation. Keep new utility-style services static unless state is required.

### Tests

**Unit tests** (`tests/Unit/`) test PHP services in isolation using PHPUnit mocking. No filesystem or subprocess access.

**E2E tests** (`tests/E2E/`) extend `CommandTestCase`, which:
- Creates a `TestProjectFixture` (temp dir under `sys_get_temp_dir()`) in `setUp()`
- Tears it down in `tearDown()`
- Provides `executeCommand(string $name, array $input = [])`, `assertCommandSuccess()`, `assertOutputContains()`, `assertConfigValue()`
- Uses `ob_start()`/`ob_get_clean()` to capture `passthru()` output as `$this->strayOutput`

When writing E2E tests, always `chdir($this->fixture->getProjectDir())` and `putenv('INIT_CWD=...')` before executing commands.

### PHPStan
Level 5, strict. A baseline file (`phpstan-baseline.neon`) tracks accepted violations. Run `task analyze:baseline` after intentional type changes, not to hide new issues.
