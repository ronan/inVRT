# Copilot Instructions for inVRT

## What This Project Is

inVRT is a **Symfony Console CLI application** (not Laravel) for running Visual Regression Testing (VRT) against cms-driven websites (Drupal, Backdrop, and Wordpress). The tool is able to capture the authenticated and unauthenticated user experience on multiple web environments (local, stage, live), and can simulate different devices (desktop/mobile) by setting the viewport size for screenshots.

PHP orchestrates configuration and runs the cli; bash scripts handle crawling (wget); Node.js tools (Playwright, BackstopJS) handle browser automation and screenshot comparison.

The tool is built of composable parts and uses environment variables internally to make configuration passing easy between processes and to allow flexibility with the individual parts.

The codebase is somewhat language agnostic. Use the right language for job at hand. We favor using PHP since the target testable platforms are PHP-based CMSs, but we also use bash and Node.js where appropriate.

## Required documentation:

Save implementation plans to the `docs/planning/agent-plans/` directory.

Do not read past plans when implementing new plans as they may not represent the current desired behavior.

Write the plan before you begin the implementation. Ask for permission before implementing the plan. Do not ask until the plan is in `docs/planning/agent-plans/`.

Never commit anything to git.

## Track tasks with the todo file

The todo file (`TODO.md`) uses markdown checkbox syntax.

Further instructions and requirements for a particular todo item may be indented underneath the item. When creating todo's keep these brief.

When you **complete** a task:
1. Move the item (and any sub-items) to `docs/planning/TODO-DONE.md` under the appropriate section header.
2. Remove it from `TODO.md`.

Do not just check off items in `TODO.md` — move them.

Don't track todo's or task progress in any other system.

## Communicating

Use simple clear language. There is no need for niceties and chit chat. Be brief.

If you have a question, ask it. If you don't understand something, ask for clarification.

Show the output of terminal commands you run to test your code, so I can see the results and understand your thought process.

## Writing Code

Follow the [Coding Standards](docs/developer/en/CODING_STANDARDS.md)

Write clean, maintainable and modern code. Favor terse and readable code.

Use well regarded third party libraries where it can reduce lines of code.

Use modern PHP features and Symfony Console code style conventions.

Use convenience features such as maps, shorthand lambda functions and the spread operator to maintain code readability.

Reduce boilerplate.

Use short clear variable names.

Write clear, concise comments where needed to explain non-obvious code.

Code should pass tests and linting: `task test`

### PHP/Console Specifics

Add a verbosity level for all calls to $logger

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

`docs/user/en/usage.md` is for humans. Keep it brief.

`docs/developer/en/APP_SUMMARY.md` is for agents. Describe every new behavior here. Aim to be able to rebuild the application from scratch using this document. Do not describe implementation in this document.

Document new features in the usage docs before building them. Explain all inputs, give examples, show outputs.

## Architecture

```
Symfony Console Commands (src/cli/Commands/)
         ↓
  InVRT\Core\Runner (src/core/Runner.php)
         ↓
  InVRT\Core\Configuration (src/core/Configuration.php)
  InVRT\Core\Service\LoginService, CookieService
         ↓
  Node.js (src/js/*.js, Playwright, BackstopJS)
```

The codebase is split into two layers:

- **`src/core/`** (`InVRT\Core\`) — framework-independent business logic. `Configuration` loads, merges, and exports config. `Runner` orchestrates crawl, reference, test, init, and config operations. `Service\LoginService` and `Service\CookieService` are utility services. Core accepts a PSR-3 `LoggerInterface` for output.
- **`src/cli/`** (`App\`) — thin Symfony Console wiring. Commands extend `BaseCommand`, call `$this->boot($opts, $io)`, then delegate to `$this->runner`.

`BaseCommand::boot()` creates a `Configuration` from resolved env vars + options, exports it to the process environment, creates a `ConsoleLogger` wrapping the Symfony output, and builds a `Runner`. Commands call `$this->runner->crawl()` etc. and return the exit code.

**Configuration merging** in `Configuration::resolve()` processes sources in this order — earlier sources win (highest precedence first):

1. `$env` array passed to constructor (includes INVRT_PROFILE, INVRT_ENVIRONMENT, INVRT_DEVICE from CLI opts + process env)
2. `devices.<name>` block from YAML
3. `profiles.<name>` block from YAML
4. `environments.<name>` block from YAML
5. `settings` block from YAML
6. Hard-coded defaults (`ConfigSchema::DEFAULTS`)

Resolved values are exported as `putenv()` environment variables so Node scripts can read them.

Refer to [The configuration documentation](docs/user/en/configuration.md) for details on how configuration works.

## Key Conventions

### Namespaces & File Layout
- `App\Commands\` → `src/cli/Commands/`
- `App\Input\` → `src/cli/Input/`
- `InVRT\Core\` → `src/core/`
- `InVRT\Core\Service\` → `src/core/Service/`
- `Tests\Unit\` → `tests/Unit/`
- `Tests\E2E\` → `tests/e2e/`
- `Tests\Fixtures\` → `tests/fixtures/`

### Adding a New Command
1. Use Symfony's attribute-based command registration with `#[AsCommand(name: '...', description: '...', help: '...')]`
2. Prefer an invokable command class with `public function __invoke(SymfonyStyle $io, #[MapInput] InvrtInput $opts): int`
3. Extend `BaseCommand` — it handles config loading, env export, runner creation, and optional login
4. Inside `__invoke()`, call `$result = $this->boot($opts, $io)`, return if not `Command::SUCCESS`, then call `$this->runner->methodName()`
5. Add business logic to `InVRT\Core\Runner` as a new method, using `$this->logger` for output
6. Register the new command in `src/cli/invrt.php` via `$container->autowire(NewCommand::class)->setPublic(true)` and add it to the app loop
7. Use `InvrtInput` with `#[MapInput]` for the shared `--profile`, `--device`, and `--environment` options instead of defining those options per command
8. Return Symfony `Command::SUCCESS` or `Command::FAILURE` codes explicitly
9. Use `$this->requiresLogin = false` in the command class if login should be skipped; pass `requiresConfig: false` to `boot()` if the config file need not exist

### Static Service Classes
`LoginService` and `CookieService` (both in `InVRT\Core\Service\` → `src/core/Service/`) are entirely static — no instantiation. Keep new utility-style services static unless state is required.

### Tests

**Unit tests** (`tests/Unit/`) test PHP services in isolation using PHPUnit mocking. No filesystem or subprocess access.

**CLI end-to-end tests** live in `tests/bats/` and run the real `bin/invrt` binary via Bats.

- Use `tests/bats/test_helper.bash` for shared setup, command runners, YAML helpers, and webserver lifecycle helpers.
- Each test cleans its own artifact directory at setup time and preserves outputs afterward for inspection.
- Prefer `/scratch/tests/` for artifacts; the helper falls back to `scratch/tests/` when `/scratch/tests/` is unavailable on the host.
- Workflow tests should use the real PHP built-in server against `tests/fixtures/website/`.
- Interactive CLI flows should be exercised through a pseudo-TTY (`script`), not by mocking Symfony input classes.

### PHPStan
Level 5, strict. A baseline file (`tooling/phpstan-baseline.neon`) tracks accepted violations. Run `task baseline:phpstan` after intentional type changes, not to hide new issues.
