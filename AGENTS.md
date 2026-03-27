# Copilot Instructions for inVRT

## What This Project Is

inVRT is a **Symfony Console CLI application** (not Laravel) for running Visual Regression Testing (VRT) against cms-driven websites (Drupal, Backdrop, and Wordpress). The tool is able to capture both the authenticated and unauthenticated user experience, and can simulate different devices (desktop/mobile) by setting the viewport size for screenshots.

PHP orchestrates configuration and runs the cli; bash scripts handle crawling (wget); Node.js tools (Playwright, BackstopJS) handle browser automation and screenshot comparison.

The tool is built of composable parts and uses environment variables internally to make configuration passing easy between processes and to allow flexibility with the individual parts.

The codebase is somewhat language agnostic. Use the right language for job at hand. We favor using PHP since the target testable platforms are PHP-based CMSs, but we also use bash and Node.js where appropriate.

## How to communicate with the team

Before you act: communicate. Create a brief outline of your plan of action and ask the user before proceeding. Your brief should allow you to repeat the plan without context. You should be able to apply the plan again to different problems in the future.

Use simple clear language. There is no need for niceties and chit chat. Don't tell me how good my ideas are.

If you have a question, ask it. If you don't understand something, ask for clarification.

If you see a problem, point it out. 

If you have an idea for improvement, share it.

When you create a script to test your code, save the script to tools/scripts so that it can be reused.

Save implementation plans to the `plans/` directory so they are tracked with the project.

Show the output of terminal commands you run to test your code, so I can see the results and understand your thought process.

Get approval for refactors and new dependencies before implementing them. Ask the user before proceeding, don't just propose and continue.

## How to Write Code for This Project

Follow the [Coding Standards](docs/CODING_STANDARDS.md)

Write clean, maintainable and modern code. Favor terse and readable code.

Use well regarded third party libraries where it can reduce lines of code.

Use modern PHP features and Symfony Console code style conventions.

Use convenience features such as maps, shorthand lambda functions and the spread operator to maintain code readability.

Reduce boilerplate.

Use short clear variable names.

Write clear, concise comments where needed to explain non-obvious code.

Code should pass tests and linting: `task test`

### PHP/Console Specifics

Add a verbosity level for all calls to $output->writeln.

## Testing

All code should be tested.

Write tests with PHPUnit, including end-to-end tests that execute real bash scripts.

Don't test at too fine a detail.

Don't test glue code.

Test the behavior not the implementation.

Use mocks and stubs for external dependencies in unit tests.

Use real subprocess execution in E2E tests to verify that the bash scripts work as expected.

Clean up after testing. E2E tests should use temporary directories and remove them after tests run.

### Testing Tools

Run `task test` in the terminal to test and lint code.


## The Usage Docs (documentation first development)

The functionality of the inVRT app is described in the [usage docs](docs/usage.md).

Document new features in the usage docs before building them. Explain all inputs, give examples, show outputs.

Mark incomplete functionality in the usage docs as "FUTURE FEATURE" until it is implemented. Remove the "FUTURE FEATURE" tag once a feature is implemented and passes end to end tests.

Defer to usage docs for business logic and behavior and suggest updates where there are gaps.


## Architecture

```
Symfony Console Commands (src/Commands/)
         ↓
  Service Layer (src/Service/)
         ↓
  Bash scripts (src/*.sh)  ←→  Node.js (src/*.js, Playwright, BackstopJS)
```

Most commands extend `BaseCommand`, which initialises `EnvironmentService`, optionally invokes `LoginService`, then calls `passthru()` on a bash script. Each such command returns a bash script filename via the abstract `getScriptName(): string` method.

**Exceptions:** `InitCommand` and `ConfigCommand` extend `Command` directly and override `execute()` themselves. `InitCommand` has no `EnvironmentService` dependency. `ConfigCommand` returns `''` from `getScriptName()`.

**Configuration merging** in `EnvironmentService` processes sections in this order — later sections overwrite earlier ones (highest precedence last):

1. `environments.<name>` block — applied first
2. `profiles.<name>` block — overwrites environment values
3. `devices.<name>` block — applied last, highest precedence

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
2. Call `$this->setName('name')->setDescription('...')` inside `configure()`
3. Implement `getScriptName(): string` returning the corresponding bash script filename
4. Call `parent::configure()` last in `configure()` to add the shared `--profile`, `--device`, `--environment` options
5. The base `execute()` handles env init, login, and script execution — only override it if the command has special behaviour (see `ConfigCommand`)

### Static Service Classes
`LoginService` and `CookieService` are entirely static — no instantiation. Keep new utility-style services static unless state is required.

### Tests

**Unit tests** (`tests/Unit/`) test PHP services in isolation using PHPUnit mocking. No filesystem or subprocess access.

**E2E tests** (`tests/E2E/`) extend `CommandTestCase`, which sets up a `TestProjectFixture` temp directory and a Symfony `Application` with all commands registered. `setUp()` calls `$this->fixture->setEnvironmentVariable()` to set `INVRT_DIRECTORY` — you do not need to set it manually.

`CommandTestCase` methods:
- `executeCommand(string $name, array $input = [])` — run a command, returns `CommandTester`
- `executeCommandWithOutputCapture(string $name, array $input = [])` — same, but also buffers subprocess (`passthru`) output into `$this->strayOutput`
- `getOutput()` — Symfony console output from last command
- `getExitCode()` — exit code from last command
- `assertCommandSuccess()` — assert exit code 0
- `assertCommandFailure(?int $code = null)` — assert non-zero (or specific) exit code
- `assertOutputContains(string $expected)` / `assertOutputNotContains(string $notExpected)`
- `assertConfigFileExists()` / `assertConfigFileNotExists()`
- `assertConfigValue(string $dotKey, mixed $expected)` — dot-notation key into config array
- `assertStrayOutputContains(string $expected)` / `assertStrayOutputNotContains(string $notExpected)`

`TestProjectFixture` helpers:
- `writeMinimalConfig()` — base project/settings only
- `writeConfigWithProfiles()` — includes profiles block
- `writeConfigWithEnvironments()` — includes environments block
- `writeConfigWithDevices()` — includes devices block
- `writeConfig(array $config)` — write arbitrary config
- `writeCookiesFile(string $profile, string $env)` — write a cookies.json
- `setEnvironmentVariable()` / `unsetEnvironmentVariable()` — set/clear `INVRT_DIRECTORY`

When writing E2E tests, do not manually set `INVRT_DIRECTORY` or `INIT_CWD` — `CommandTestCase.setUp()` handles this via `$this->fixture->setEnvironmentVariable()`.

### PHPStan
Level 5, strict. A baseline file (`phpstan-baseline.neon`) tracks accepted violations. Run `task baseline:phpstan` after intentional type changes, not to hide new issues.
