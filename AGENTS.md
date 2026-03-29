# Copilot Instructions for inVRT

## What This Project Is

inVRT is a **Symfony Console CLI application** (not Laravel) for running Visual Regression Testing (VRT) against cms-driven websites (Drupal, Backdrop, and Wordpress). The tool is able to capture the authenticated and unauthenticated user experience on multiple web environments (local, stage, live), and can simulate different devices (desktop/mobile) by setting the viewport size for screenshots.

PHP orchestrates configuration and runs the cli; bash scripts handle crawling (wget); Node.js tools (Playwright, BackstopJS) handle browser automation and screenshot comparison.

The tool is built of composable parts and uses environment variables internally to make configuration passing easy between processes and to allow flexibility with the individual parts.

The codebase is somewhat language agnostic. Use the right language for job at hand. We favor using PHP since the target testable platforms are PHP-based CMSs, but we also use bash and Node.js where appropriate.

## Required documentation:

Save implementation plans to the `plans/` directory so they are tracked with the changes.

Track objectives in the [todo file](TODO.md).

Never commit anything to git.

## Track tasks with the todo file

The todo file is a markdown file with checkbox syntax.

Further instructions and requirements for a particular todo item may be indented underneath the item. When creating todo's keep these brief.

If you complete a task you can put an 'x' between the square brackets on that line to indicate a checked box in markdown.

Don't track todo's or task progress in any other system

## How to communicate with the team

Use simple clear language. There is no need for niceties and chit chat. Be brief.

Before you act Create a brief outline of your plan of action and ask the user before proceeding.

If you have a question, ask it. If you don't understand something, ask for clarification.

If you see a problem, point it out.

If you have an idea for improvement, share it.

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

Test the happy path

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

Document new features in the usage docs before building them. Explain all inputs, give examples, show outputs. Use code snippets.

Defer to usage docs for business logic and behavior and suggest updates where there are gaps.

## Architecture

```
Symfony Console Commands (src/Commands/)
         ↓
  Service Layer (src/Service/)
         ↓
  Bash scripts (src/*.sh)  ←→  Node.js (src/*.js, Playwright, BackstopJS)
```

Most commands extend `BaseCommand` and expose an invokable `__invoke()` method. `EnvironmentService` is injected into `BaseCommand` via constructor DI — the entry point (`src/invrt.php`) uses `symfony/dependency-injection` with a `ContainerBuilder` to wire services and commands automatically. Commands call `$this->boot($opts, $io)` to initialise the environment and handle login, then work directly with the returned env array.

For process execution, commands should use `executeScript()` for bash-driven flows and `runBackstop()` for BackstopJS flows so subprocess handling stays consistent.

**Exceptions:** `InitCommand` is a standalone invokable command because it does not need resolved inVRT environment variables and is registered directly without DI. `ConfigCommand` still extends `BaseCommand`, but calls `$this->boot($opts, $io, requiresConfig: false)` because it needs the project directory without requiring an existing config file.

**Configuration merging** in `EnvironmentService` processes sections in this order — later sections overwrite earlier ones (highest precedence last):

1. `environments.<name>` block — applied first
2. `profiles.<name>` block — overwrites environment values
3. `devices.<name>` block — applied last, highest precedence

Resolved values are exported as `putenv()` environment variables so bash and Node scripts can read them.

Refer to [The configuration documentation](docs/configuration.md) for details on how configuration works. 

## Key Conventions

### Namespaces & File Layout
- `App\Commands\` → `src/Commands/`
- `App\Service\` → `src/Service/`
- `Tests\Unit\` → `tests/Unit/`
- `Tests\E2E\` → `tests/e2e/`
- `Tests\Fixtures\` → `tests/fixtures/`

### Adding a New Command
1. Use Symfony's attribute-based command registration with `#[AsCommand(name: '...', description: '...', help: '...')]`
2. Prefer an invokable command class with `public function __invoke(SymfonyStyle $io, #[MapInput] InvrtInput $opts): int`
3. Extend `BaseCommand` when the command needs resolved inVRT environment variables, config loading, or login handling
4. Inside `__invoke()`, call `$result = $this->boot($opts, $io)`, check `if (is_int($result)) return $result;`, then work directly with `$result` as the env array
5. Register the new command in `src/invrt.php` via `$container->autowire(NewCommand::class)->setPublic(true)` and add it to the app loop
6. Use `executeScript()` for bash-driven commands and `runBackstop()` for BackstopJS flows instead of rebuilding process bootstrapping in each command
7. Use `InvrtInput` with `#[MapInput]` for the shared `--profile`, `--device`, and `--environment` options instead of defining those options per command
8. Return Symfony `Command::SUCCESS` or `Command::FAILURE` codes explicitly and add a verbosity level to every `$io->writeln()` call
9. Only skip `BaseCommand` for true exceptions that do not need environment bootstrapping, such as `init`

### Static Service Classes
`LoginService` and `CookieService` are entirely static — no instantiation. Keep new utility-style services static unless state is required.

### Tests

**Unit tests** (`tests/Unit/`) test PHP services in isolation using PHPUnit mocking. No filesystem or subprocess access.

**E2E tests** (`tests/e2e/`) extend `CommandTestCase`, which sets up a `TestProjectFixture` temp directory and a Symfony `Application` with all commands registered. `setUp()` calls `$this->fixture->setEnvironmentVariable()` to set `INVRT_DIRECTORY` — you do not need to set it manually.

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
