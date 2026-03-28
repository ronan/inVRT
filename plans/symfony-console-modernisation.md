# Symfony Console Modernisation Plan

## Phase 1 — Completed

- ✅ `#[AsCommand]` attribute on all commands — removed `setName()`/`setDescription()` from `configure()`
- ✅ `InputOption::VALUE_REQUIRED` for `--profile`, `--device`, `--environment`
- ✅ Fixed raw `0`/`1` exit codes in `ConfigCommand`
- ✅ `executeCommand(InputInterface, SymfonyStyle, array $env)` hook on `BaseCommand` — eliminated `execute()` duplication; `execute()` marked `final`
- ✅ `requiresConfig()` hook so `ConfigCommand` opts out of the config-required check
- ✅ `SymfonyStyle` threaded through all commands; `$io->success()` / `$io->error()` adopted
- ✅ Removed dead `getScriptName()` stubs and unused `Command` imports

---

## Phase 2 — Symfony 8 Invokable Commands

### Background (research findings)

Symfony 8 docs explicitly label `configure()` + `execute()` as **"Legacy Syntax"** and say **invokable commands are recommended**. Key findings from the installed `symfony/console` 8.x source:

- **`#[AsCommand]` accepts `help:` and `usages:`** — `setHelp()` in `configure()` can be removed.
- **`__invoke()` replaces `execute()`** — when a non-`Command` callable is passed to `$app->addCommand()`, Symfony wraps it in the internal `InvokableCommand` class, which uses reflection to auto-inject typed parameters (`SymfonyStyle`, `InputInterface`, `OutputInterface`, etc.).
- **`#[Option]` / `#[Argument]` parameter attributes** — replace `addOption()` / `addArgument()` calls in `configure()`.
- **`#[MapInput]` DTO** — maps options/arguments onto a value object via annotated public properties. Ideal for sharing `--profile / --device / --environment` across commands.
- **`configure()` becomes unnecessary** once options move to `__invoke()` params.
- **`Application::add()` was removed in 8.0** — `addCommand(callable|Command)` is the only API; callables (classes with `__invoke`) are accepted directly.

---

### Change 1 — Move `help` into `#[AsCommand]`

`#[AsCommand]` in Symfony 8 accepts `help:` and `usages:`. Move help text from `setHelp()` into the attribute and remove the now-empty `configure()` override.

Before:
```php
#[AsCommand(name: 'init', description: 'Initialize a new inVRT project')]
class InitCommand extends Command {
    protected function configure(): void {
        $this->setHelp('Initializes a new inVRT project...');
    }
```

After:
```php
#[AsCommand(
    name: 'init',
    description: 'Initialize a new inVRT project',
    help: 'Initializes a new inVRT project...',
)]
class InitCommand {
```

---

### Change 2 — Create `InvrtInput` DTO

Replace three repeated `addOption()` calls with a `#[MapInput]`-compatible DTO. `#[MapInput]` reads **public properties** annotated with `#[Option]`/`#[Argument]`:

```php
// src/Input/InvrtInput.php
use Symfony\Component\Console\Attribute\Option;

class InvrtInput {
    #[Option(description: 'Profile name', shortcut: 'p')]
    public string $profile = 'anonymous';

    #[Option(description: 'Device type', shortcut: 'd')]
    public string $device = 'desktop';

    #[Option(description: 'Environment name', shortcut: 'e')]
    public string $environment = 'local';
}
```

---

### Change 3 — Refactor `BaseCommand` to abstract invokable helper

`BaseCommand` currently extends `Command` to hook into `execute()`. With `__invoke()`, it no longer needs to extend `Command` — it just provides `boot()` / `handleLogin()` shared logic.

```php
// src/Commands/BaseCommand.php — no longer extends Command
abstract class BaseCommand {
    protected function boot(InvrtInput $opts, SymfonyStyle $io, bool $requiresConfig = true): array|int {
        $env = (new EnvironmentService($opts->profile, $opts->device, $opts->environment))
            ->initialize($io, $requiresConfig);
        $login = LoginService::loginIfCredentialsExist(
            $env['INVRT_USERNAME'] ?? '', $env['INVRT_PASSWORD'] ?? '',
            $env['INVRT_URL'] ?? '', $env['INVRT_COOKIES_FILE'] ?? '',
            $io,
        );
        if ($login !== Command::SUCCESS) return $login;
        return $env;
    }

    protected function joinPath(string ...$segments): string {
        return implode(DIRECTORY_SEPARATOR, $segments);
    }
}
```

---

### Change 4 — Migrate commands to `__invoke()`

Each command:
- Removes `configure()` entirely
- Implements `__invoke(SymfonyStyle $io, #[MapInput] InvrtInput $opts): int`
- `InitCommand` — standalone invokable, no base class, no options DTO (no options at all)

```php
#[AsCommand(name: 'crawl', description: '...', help: '...')]
class CrawlCommand extends BaseCommand {
    public function __invoke(SymfonyStyle $io, #[MapInput] InvrtInput $opts): int {
        $result = $this->boot($opts, $io);
        if (\is_int($result)) return $result;  // login failed
        // ... work with $result ($env array)
    }
}
```

`ConfigCommand` passes `requiresConfig: false`:
```php
$result = $this->boot($opts, $io, requiresConfig: false);
```

---

### Change 5 — Clean up `src/invrt.php` entry point

- Remove inline comments (`// Create the application`, `// Register commands`, etc.)
- Remove redundant `$app->setName()` — the emoji name is set in the `Application` constructor already

---

## Todos (Phase 2)

1. `p2-help-to-attribute` — Add `help:` to every `#[AsCommand]`, remove `setHelp()` calls, remove now-empty `configure()` methods
2. `p2-invrt-input-dto` — Create `src/Input/InvrtInput.php` with `#[Option]` property attributes for profile/device/environment
3. `p2-refactor-base-command` — Strip `extends Command` from `BaseCommand`; replace `final execute()` + `executeCommand()` with a `boot()` helper returning `array|int`
4. `p2-migrate-commands-invoke` — Migrate all commands to `__invoke(SymfonyStyle $io, #[MapInput] InvrtInput $opts): int`; `InitCommand` becomes a pure standalone invokable
5. `p2-cleanup-entrypoint` — Remove comments and redundant `setName()` from `src/invrt.php`

## Notes

- All changes must pass `task test`; run `task baseline:phpstan` after type changes
- `InvokableCommand` is `@internal` — the public API (`addCommand(callable)`) is stable
- `EnvironmentService` and `LoginService` type-hint `OutputInterface` — `SymfonyStyle` satisfies this, no changes needed
- `ConfigCommand` needs `requiresConfig: false` passed to `boot()` via named argument
