# Plan: Create framework-independent core library

**Todo:** `TODO.md` → `## Tech Debt` → `Create a core php library independent of any framework and with minimal dependencies`

## Problem

The current app keeps reusable business logic and Symfony Console wiring mixed together under `src/`.
Configuration loading, command orchestration, process execution, and some command-specific behavior are spread across:

- `src/Commands/BaseCommand.php`
- `src/Commands/CrawlCommand.php`
- `src/Commands/ReferenceCommand.php`
- `src/Commands/TestCommand.php`
- `src/Commands/ConfigCommand.php`
- `src/Service/ConfigurationService.php`
- `src/Service/InvrtConfiguration.php`
- `src/Service/LoginService.php`
- `src/Service/CookieService.php`

That makes the main runtime tightly coupled to Symfony Console, Symfony DI, Symfony Config, and Symfony Process. The todo requires a reusable `/core` library with a `Configuration` API and a `Runner` API, plus a thin `/cli` layer built on top.

## Current state

- `src/invrt.php` boots a Symfony Console app and registers commands through `ContainerBuilder`.
- Commands extend `BaseCommand`, which handles config resolution, optional login, running bash scripts, and running BackstopJS.
- `ConfigurationService` loads config from env + YAML, merges `settings`, `environments`, `profiles`, and `devices`, interpolates `INVRT_*` placeholders, and exports the resolved values back to the environment.
- `InvrtConfiguration` defines the config schema and defaults through `symfony/config`.
- `LoginService` and `CookieService` already look like reusable utility services, but `LoginService` still depends on the current process/output approach.
- Tests are split between PHPUnit unit tests and command-level/E2E coverage, so the refactor must preserve public command behavior.

## Proposed approach

Refactor in phases so behavior stays stable:

1. Create a new `/core` package namespace for reusable configuration, orchestration, and utility logic.
2. Move command behavior into a framework-light `Runner` that exposes `init()`, `config()`, `crawl()`, `reference()`, and `test()`.
3. Replace `ConfigurationService` with a core `Configuration` object that owns loading, reads, writes, env overlays, and persistence.
4. Reduce the `/cli` layer to Symfony-specific input parsing, DI wiring, and output formatting.
5. Update tests so existing command behavior still passes while adding direct coverage for the new core entrypoints.

Confirmed direction: `/core` should be framework-independent in design and public API, but it may keep small Symfony packages where they are useful. Do not introduce extra adapter layers just to avoid Symfony. For text output from core flows, prefer accepting a PSR-3 logger.

## Implementation todos

1. **Define the core package boundaries**
   - Create `/core/src/InVRT/Core/`.
   - Decide which current `src/` classes become reusable core classes versus thin CLI wrappers.
   - Keep CLI-only concerns in `/cli`, including Symfony attributes, `SymfonyStyle`, and application bootstrapping.

2. **Design and introduce `InVRT\Core\Configuration`**
   - Accept a config filepath and environment variable array in the constructor.
   - Provide `get($key, $default)`, `set($key, $value)`, and `write()`.
   - Move config merging/interpolation logic out of `ConfigurationService`.
   - Preserve current precedence rules and derived path resolution.
   - Decide whether schema validation stays via an adapter or is rewritten in core-friendly form.

3. **Design and introduce `InVRT\Core\Runner`**
   - Expose `init()`, `config()`, `crawl()`, `reference()`, and `test()`.
   - Move orchestration out of `BaseCommand` and command classes.
  - Centralize subprocess launching for bash and Node/Backstop flows in reusable core helpers without adding unnecessary adapter layers.
  - Accept a PSR-3 logger for text output so CLI formatting does not leak into core.

4. **Extract reusable services into core**
   - Move or adapt `LoginService` and `CookieService` under `/core`.
   - Extract crawl/reference/test helper logic currently embedded in commands.
  - Keep filesystem and process interactions simple; reuse Symfony packages directly where that meaningfully reduces code.

5. **Create the `/cli` wrapper layer**
   - Move the Symfony Console entrypoint and command classes into `/cli`.
   - Rewrite commands as thin wrappers that map CLI input to `Runner` calls.
   - Keep the current command names, options, and observable behavior unchanged.

6. **Update docs, autoloading, and tests**
   - Update `composer.json` autoload rules for `InVRT\Core\` and the new `/cli` structure.
   - Update architecture and usage docs where the runtime structure changes.
   - Refactor/add tests so the new core classes are directly covered and existing command-level tests still pass.

7. **Validate and complete**
   - Run the existing test/lint workflow (`task test`).
   - Fix breakages caused by the move.
   - Check off the tech debt todo in `TODO.md` only after the refactor is complete and tests pass.

## Risks and decisions to confirm

- How much of the current env-var-driven behavior should remain the canonical internal API versus being wrapped by richer value objects.
- Whether the directory move should be done in one pass (`src` → `cli`) or in compatibility stages to reduce churn.
- How much of current command test coverage should be migrated to direct core tests versus preserved as wrapper-level tests.

## Validation

- Preserve command names and options.
- Preserve current config precedence and derived environment variables.
- Preserve current bash and BackstopJS behavior.
- Keep the existing test suite green with `task test`.
