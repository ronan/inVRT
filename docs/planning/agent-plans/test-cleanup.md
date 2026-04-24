# Plan: Test Cleanup

Implements the "Test Cleanup" section of TODO.md:

- Remove unit tests. Add or update e2e tests for any edge cases that are missing.
- Remove phpstan, phpunit and rector.

Bats remains the sole test harness. PHP CS Fixer is retained (lint/format is
separate from the three tools being removed).

## 1. Audit unit-test coverage vs bats coverage

| Unit test | What it covers | Bats coverage |
|---|---|---|
| `ConfigurationTest::testNoWarningsForValidConfig` | valid config produces no warnings | Implicit: every bats `init/check/crawl` test uses a valid config and succeeds silently. |
| `ConfigurationTest::testWarningAndContinuesForUnknownKeys` | unexpected keys warn but don't fail | `cli.bats` — "config: shows warning but succeeds when config has unexpected keys". |
| `RunnerTest::testConfigurePlaywrightWritesConfigFile` | `configurePlaywright` writes file with expected contents | `workflow.bats` asserts `.invrt/data/anonymous/playwright.config.ts` exists after reference flow. |
| `RunnerTest::testConfigurePlaywrightCreatesDirectoryIfMissing` | missing nested dir is created | Same workflow test — the dir does not pre-exist in fixtures. |
| `RunnerTest::testConfigurePlaywrightFailsWhenConfigFileEmpty` | non-zero exit when required env missing | Not covered. Low-value edge case (internal guard only reachable with broken config). Drop without replacement. |
| `NodeOutputParserTest` (all 9 cases) | pino-JSON → PSR-3 log-level mapping, line buffering | Implicit: bats tests assert human-facing strings in command output produced through this parser (e.g. "Crawling completed", "Site check complete", "warning"/"unexpected"). Log-level routing is an internal implementation detail, not a behavior worth a dedicated e2e. Drop without replacement. |
| `CookieServiceTest` (7 cases) | JSON → Netscape cookie conversion with defaults and edge cases | Not currently exercised end-to-end. Login/auth fixtures don't exist yet (see TODO "Replace cookies.json with session.json" and "Test drupal auth support" items under `[#]` not-ready). Dropping this test reduces coverage of an internal helper that is slated for replacement. Acceptable per TODO directive; no new e2e required because the feature it guards is scheduled for removal. |

Conclusion: no new bats tests are needed — all behaviors the unit tests
protected are either already covered or are internal details / slated-for-removal code.

## 2. Changes to make

### Remove files

- `tests/Unit/` (entire directory: `ConfigurationTest.php`, `CookieServiceTest.php`, `NodeOutputParserTest.php`, `RunnerTest.php`)
- `tests/E2E/` (empty directory, leftover)
- `tooling/config/phpunit.xml`
- `tooling/config/phpstan.neon`
- `tooling/config/phpstan-baseline.neon`
- `tooling/config/rector.php`

### Edit `composer.json`

Drop from `require-dev`:
- `phpunit/phpunit`
- `rector/rector`
- `phpstan/phpstan`

Keep `friendsofphp/php-cs-fixer` (not in scope).

Remove `autoload-dev` (`Tests\\` PSR-4 mapping) — no longer needed.

### Edit `Taskfile.yml`

- Change `test` deps from `[fix, test:phpstan, test:php, test:bats]` to `[fix, test:bats]`.
- Delete tasks: `test:php`, `test:php:unit`, `test:coverage`, `test:modernize`, `fix:modernize`, `test:phpstan`, `baseline:phpstan`.
- Change `watch` deps: drop `test:php:unit`.

### Edit `AGENTS.md` and `.github/copilot-instructions.md`

- Drop "### PHPStan" section.
- Update the "Testing" section:
  - Remove "Write tests with PHPUnit, including end-to-end tests that execute real bash scripts." → replace with a single-line statement that bats is the sole test harness.
  - Remove the "Unit tests (`tests/Unit/`) test PHP services in isolation using PHPUnit mocking." paragraph.
- Update namespaces list: drop `Tests\Unit\`, `Tests\E2E\`, `Tests\Fixtures\` rows (no test PSR-4 anymore).

### Reinstall vendor tree

Run `composer update --lock` (or `composer install` after the manifest edit) so
`composer.lock` and `vendor/` no longer contain the removed tools. The vendor
directory is committed in this repo, so the install is part of the change.

### Update `TODO.md`

Move the "Test Cleanup" section (with both bullets) to `docs/planning/TODO-DONE.md`
after implementation.

## 3. Validation

1. `task test` — must pass and show only the bats suite.
2. `grep -rn "phpunit\|phpstan\|rector" Taskfile.yml composer.json AGENTS.md` — no matches.
3. `ls tests/` — shows only `README.md`, `bats/`, `fixtures/`.

## 4. Out of scope

- The "Replace cookies.json with session.json" item: still on TODO.
- Any change to php-cs-fixer setup.
- Docker image changes (Dockerfile does not reference these tools).
- Removing `Tests\Fixtures` — directory only contains non-PHP fixtures; the
  PSR-4 mapping is only in `autoload-dev`, which is being dropped entirely.
