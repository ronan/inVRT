# Copilot Instructions for inVRT

## What This Project Is

inVRT is a **Symfony Console CLI application** (not Laravel) for running Visual Regression Testing (VRT) against cms-driven websites (Drupal, Backdrop, and Wordpress). The tool is able to capture both the authenticated and unauthenticated user experience, and can simulate different devices (desktop/mobile) by setting the viewport size for screenshots.

PHP orchestrates configuration and runs the cli; bash scripts handle crawling (wget); Node.js tools (Playwright, BackstopJS) handle browser automation and screenshot comparison.

The tool is built of composable parts and uses environment variables internally to make configuration passing easy between processes and to allow flexibility with the individual parts.

The codebase is somewhat language agnostic. Use the right language for job at hand. We favor using PHP since the target testable platforms are PHP-based CMSs, but we also use bash and Node.js where appropriate.

Write clean, maintainable and modern code.

Use modern PHP features and Symfony Console conventions.

Use convenience features such as maps, shorthand lambda functions and the spread operator to maintain code readability.

Reduce boilerplate.

Use short clear variable names.

Write clear, concise comments where needed to explain non-obvious code.

Code should pass tests and linting: `task test`

Write tests with PHPUnit, including end-to-end tests that execute real bash scripts. Don't test at too fine a detail. Focus on testing the overall behavior and integration of components, not implementation details. Use mocks/stubs for external dependencies in unit tests, but use real subprocess execution in E2E tests to verify actual CLI workflows.

## Commands

```bash
# Testing
task test                   # Run full PHPUnit suite (--testdox output)
task test:e2e               # E2E tests only
task test:coverage          # Coverage report (text)

# Run a single test file
vendor/bin/phpunit tests/Unit/CookieServiceTest.php

# Run a single test method
vendor/bin/phpunit --filter testMethodName tests/Unit/CookieServiceTest.php

# Static analysis
task test:phpstan                # PHPStan level 5
task test:phpstan:baseline       # Regenerate phpstan-baseline.neon
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
