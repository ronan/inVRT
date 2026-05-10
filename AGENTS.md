# Copilot Instructions for inVRT

## What This Project Is

inVRT is a **TypeScript CLI application** built on Commander for running Visual Regression Testing (VRT) against cms-driven websites (Drupal, Backdrop, and Wordpress). The tool is able to capture the authenticated and unauthenticated user experience on multiple web environments (local, stage, live), and can simulate different devices (desktop/mobile) by setting the viewport size for screenshots.

TypeScript orchestrates configuration and runs the CLI; Node.js tools (Playwright) handle crawling, browser automation, screenshot comparison, and report generation.

The tool is built of composable parts and uses environment variables internally to make configuration passing easy between processes and to allow flexibility with the individual parts.

The codebase is somewhat language agnostic. Use the right language for job at hand. We favor TypeScript/Node.js for CLI and orchestration work, but also use bash where appropriate.

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

Use modern TypeScript and Commander code style conventions.

Use convenience features such as maps, shorthand lambda functions and the spread operator to maintain code readability.

Reduce boilerplate.

Use short clear variable names.

Write clear, concise comments where needed to explain non-obvious code.

Code should pass tests and linting: `task test`

### CLI Specifics

Add a verbosity level for all logger output.

## Testing

All code should be tested.

Tests are written as end-to-end Bats tests under `tests/bats/` that run the real `bin/invrt` binary.

Test the happy path

Don't test at too fine a detail.

Don't test glue code.

Test the behavior not the implementation.

Use real subprocess execution to verify that the bash scripts work as expected.

Clean up BEFORE testing not after. Tests should use temporary directories in `/scratch` and leave artifacts for inspection after tests run. `/scratch` is ignored by git.

### Testing Tools

Run `task test` in the terminal to test and lint code.

## The Usage Docs (documentation first development)

`docs/user/en/usage.md` is for humans. Keep it brief.

`docs/developer/en/APP_SUMMARY.md` is for agents. Describe every new behavior here. Aim to be able to rebuild the application from scratch using this document. Do not describe implementation in this document.

Document new features in the usage docs before building them. Explain all inputs, give examples, show outputs.

## Architecture

```
Commander CLI (src/ts/cli.ts)
         ↓
  Runner + Configuration (src/ts/)
         ↓
  Node.js helpers (src/js/*.js, Playwright)
```

The codebase is split into two layers:

- **`src/ts/`** — CLI/runtime orchestration. `cli.ts` registers commands with Commander, `configuration.ts` resolves/export config, and `runner.ts` orchestrates init/check/crawl/reference/test/report flows.
- **`src/js/`** — focused Node helpers used by the TypeScript runner for crawl, check, Playwright spec generation, login, and report generation.

**Configuration merging** in `Configuration::resolve()` processes sources in this order — earlier sources win (highest precedence first):

1. `$env` array passed to constructor (includes INVRT_PROFILE, INVRT_ENVIRONMENT, INVRT_DEVICE from CLI opts + process env)
2. `devices.<name>` block from YAML
3. `profiles.<name>` block from YAML
4. `environments.<name>` block from YAML
5. `project` block from YAML
6. Hard-coded defaults (`ConfigSchema::DEFAULTS`)

Resolved values are exported as `INVRT_*` process environment variables so Node scripts can read them.

Refer to [The configuration documentation](docs/user/en/configuration.md) for details on how configuration works.

## Key Conventions

### File Layout
- `src/ts/` → Commander CLI, config resolution, orchestration, process runners
- `src/js/` → Playwright/crawl/report helper scripts

### Adding a New Command
1. Register the command in `src/ts/cli.ts` with Commander.
2. Reuse the shared `--profile`, `--device`, and `--environment` options.
3. Route config loading, env export, and optional login through the shared boot helpers.
4. Add orchestration behavior to `src/ts/runner.ts`.
5. Add or reuse focused helper scripts in `src/js/` only when the behavior belongs outside the CLI layer.

### Tests

**CLI end-to-end tests** live in `tests/bats/` and run the real `bin/invrt` binary via Bats.

- Use `tests/bats/test_helper.bash` for shared setup, command runners, YAML helpers, and webserver lifecycle helpers.
- Each test cleans its own artifact directory at setup time and preserves outputs afterward for inspection.
- Prefer `/scratch/tests/` for artifacts; the helper falls back to `scratch/tests/` when `/scratch/tests/` is unavailable on the host.
- Workflow tests should use the Node static fixture server against `tests/fixtures/website/`.
- Interactive CLI flows should be exercised through a pseudo-TTY (`script`), not by mocking Symfony input classes.
