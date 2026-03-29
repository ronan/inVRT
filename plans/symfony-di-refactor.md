# Plan: Symfony Dependency Injection Refactor

**Todo:** Use symfony dependency injection for configuration passing

## Problem

Commands instantiate `EnvironmentService` manually inside `BaseCommand::boot()` with
`new EnvironmentService($opts->profile, $opts->device, $opts->environment)`. There is no DI
container. Commands use a callback-based `withEnv()` pattern that nests all command logic
inside a closure.

## Approach

Add `symfony/dependency-injection`, refactor `EnvironmentService` to be injectable (no
runtime args in constructor), wire everything with a `ContainerBuilder` in the entry point,
and remove the `withEnv()` callback pattern from commands.

---

## Steps

### 1. Add symfony/dependency-injection

Add to `composer.json` `require` block:

```json
"symfony/dependency-injection": "^8.0"
```

Run `composer install`.

### 2. Refactor EnvironmentService constructor

Move `profile`, `device`, `environment` from the constructor to `initialize()`.
Constructor becomes no-arg (only sets `$this->scriptsDir = __DIR__ . '/..'`).

```php
// Before:
public function __construct(string $profile = 'anonymous', ...)

// After:
public function __construct()
public function initialize(string $profile, string $device, string $environment, OutputInterface $output, bool $requireConfig = true): array
```

All internal `$this->profile`, `$this->device`, `$this->environment` assignments move to
the top of `initialize()`.

### 3. Set up DI container in src/invrt.php

Replace the manual `new XxxCommand()` calls with a `ContainerBuilder`:

```php
use Symfony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();
$container->autowire(EnvironmentService::class)->setPublic(true);
$container->autowire(CrawlCommand::class)->setPublic(true);
$container->autowire(ReferenceCommand::class)->setPublic(true);
$container->autowire(TestCommand::class)->setPublic(true);
$container->autowire(ConfigCommand::class)->setPublic(true);
$container->compile();

$app = new Application('📖 inVRT CLI', '1.0.0');
foreach ([CrawlCommand::class, ReferenceCommand::class, TestCommand::class, ConfigCommand::class] as $cls) {
    $app->addCommand($container->get($cls));
}
$app->addCommand(new InitCommand()); // InitCommand has no DI dependencies
$app->run();
```

### 4. Refactor BaseCommand

- Add constructor to inject `EnvironmentService`:
  ```php
  public function __construct(protected readonly EnvironmentService $env) {}
  ```
- Remove `withEnv()` entirely.
- Refactor `boot()` (now `protected`, not `private`) to use `$this->env` and accept runtime
  params explicitly:
  ```php
  protected function boot(
      InvrtInput $opts,
      SymfonyStyle $io,
      bool $requiresConfig = true,
  ): array|int {
      $env = $this->env->initialize($opts->profile, $opts->device, $opts->environment, $io, $requiresConfig);
      $loginResult = LoginService::loginIfCredentialsExist(...);
      return $loginResult !== Command::SUCCESS ? $loginResult : $env;
  }
  ```
- Keep `executeScript()` and `runBackstop()` unchanged.

### 5. Refactor each command

Replace the `withEnv()` callback pattern with direct calls:

```php
// Before:
return $this->withEnv($opts, $io, function (array $env) use ($io): int {
    $url = $env['INVRT_URL'];
    // ... logic
});

// After:
$result = $this->boot($opts, $io);
if (is_int($result)) return $result;
$url = $result['INVRT_URL'];
// ... logic
return Command::SUCCESS;
```

Commands affected: `CrawlCommand`, `ReferenceCommand`, `TestCommand`, `ConfigCommand`.

`ConfigCommand` passes `requiresConfig: false`:
```php
$result = $this->boot($opts, $io, requiresConfig: false);
```

### 6. Update tests

- Unit tests for `EnvironmentService` — update to match new constructor signature
  (no-arg) and `initialize()` signature (3 string params instead of being set at
  construction).
- Unit tests for `BaseCommand` derivatives — update any mocks of `EnvironmentService`.
- E2E tests should continue to pass with no changes (they test behavior, not
  implementation).

### 7. Run tests and lint

```
task test
```

Fix any failures.

### 8. Update AGENTS.md

Update the Architecture section to reflect the new DI-based command pattern.

### 9. Check off the todo

Mark the item as done in `TODO.md`.
