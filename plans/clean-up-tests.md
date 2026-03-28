# Plan: Clean Up Tests

**From TODO.md:**
1. Rename `tests/E2E` → `tests/e2e` and update references
2. Combine E2E tests to reduce command executions, using `CrawlCommandTest` as the example

---

## Part 1: Rename tests/E2E → tests/e2e

### Files referencing the directory path (need path updates):
- `tooling/phpunit.xml` — `<directory>../tests/E2E</directory>`
- `Taskfile.yml` — `--testdox {{.TASKFILE_DIR}}/tests/E2E`

### Files with `namespace Tests\E2E` (keep namespace, map via composer):
All 7 PHP files in `tests/E2E/` use `namespace Tests\E2E;`

PHP namespaces are case-sensitive. `composer.json` autoloads `"Tests\\": "tests/"` via PSR-4, which maps `Tests\E2E\Foo` → `tests/E2E/Foo.php`. After renaming the directory to `tests/e2e`, this mapping breaks on Linux (case-sensitive filesystem).

**Fix:** Add a specific autoload-dev entry to `composer.json`:
```json
"Tests\\E2E\\": "tests/e2e/"
```
This takes precedence over the catch-all `"Tests\\": "tests/"` entry and ensures `Tests\E2E\*` classes load from the lowercase directory. Then run `composer dump-autoload`.

### Steps:
1. `mv tests/E2E tests/e2e`
2. Add `"Tests\\E2E\\": "tests/e2e/"` to `autoload-dev` in `composer.json`
3. Run `composer dump-autoload`
4. Update `tooling/phpunit.xml` path
5. Update `Taskfile.yml` path

---

## Part 2: Combine E2E tests

The pattern: where multiple test methods run the same command with identical setup, merge their assertions into one test.

### CrawlCommandTest (5 → 3)

`testCrawlDiscoversAllPages`, `testCrawlOutputContainsStatusLine`, and `testCrawlCreatesLogFile` all call `setupCrawlFixture()` and run `crawl` with no options. Combine into one `testCrawlHappyPath` that asserts: URLs file exists with correct content, log file created, verbose output contains status line.

Keep `testRequiresConfig` and `testCrawlWithEnvironmentOption` unchanged.

### ReferenceCommandTest (3 → 2)

`testReferenceCommandCapturesScreenshots` and `testReferenceCommandOutputContainsStatusLine` both run the full reference command with the same setup. Combine into one test that asserts PNGs are created **and** output contains status line (run with `VERBOSITY_VERBOSE`).

Keep `testRequiresConfig` unchanged.

### TestCommandTest (3 → 2)

`testTestCommandRunsComparison` and `testTestCommandOutputContainsStatusLine` both seed references then run the test command with the same setup. Combine into one test that asserts test bitmaps are created **and** output contains status line.

Keep `testRequiresConfig` unchanged.

### InitCommandTest (3 → 2)

`testInitCommandCreatesProjectStructure` and `testInitCommandCreatesValidConfig` both remove `.invrt` and run `init`. Combine into one test that checks: command success, output messages, directory structure, and config content.

Keep `testInitCommandFailsWhenAlreadyInitialized` unchanged.

### ConfigCommandTest (3 → unchanged)

All 3 tests have distinct setups (config present, no config, invalid YAML). No combining needed.

---

## Summary

| File | Before | After |
|------|--------|-------|
| CrawlCommandTest | 5 | 3 |
| ReferenceCommandTest | 3 | 2 |
| TestCommandTest | 3 | 2 |
| InitCommandTest | 3 | 2 |
| ConfigCommandTest | 3 | 3 |
| **E2E total** | **17** | **12** |
| Unit tests | 16 | 16 |
| **Grand total** | **33** | **28** |

---

## Steps

1. Rename `tests/E2E` → `tests/e2e`
2. Update `composer.json` autoload-dev, run `composer dump-autoload`
3. Update `tooling/phpunit.xml` directory path
4. Update `Taskfile.yml` directory path
5. Combine CrawlCommandTest (5 → 3)
6. Combine ReferenceCommandTest (3 → 2)
7. Combine TestCommandTest (3 → 2)
8. Combine InitCommandTest (3 → 2)
9. Run `task test` — all tests pass
10. Mark TODO.md item as done
