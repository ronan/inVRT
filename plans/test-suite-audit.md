# Plan: Slim Down Test Suite

**Goal from TODO.md:** Reduce the number of PHP tests. Leave only tests of core functionality. Don't test error handling. Don't test yaml parsing or cookie handling. Simplify the config handling. Look for other ways to simplify.

## Current state: 77 tests across 9 files

---

## What counts as "core functionality"

- **EnvironmentService** — resolves config + options into env vars (merging, path construction)
- **CrawlCommand URL parsing** — parses wget log into sorted, deduplicated URL list
- **E2E commands** — crawl, reference, test, init, config executed end-to-end

Everything else (YAML parsing, cookie format conversion, error edge cases, LoginService internals) is either a third-party library concern or covered implicitly by the E2E tests.

---

## Files to delete entirely (−32 tests)

| File | Tests | Reason |
|------|-------|--------|
| `tests/Unit/InvrtCliTest.php` | 12 | Tests Symfony YAML library, `in_array()`, `array_merge()`, string concat — not our code |
| `tests/Unit/ErrorHandlingTest.php` | 12 | "Don't test error handling" — all 12 tests exercise `EnvironmentService` edge cases already covered by integration |
| `tests/Unit/CookieServiceTest.php` | 8 | "Don't test cookie handling" — CookieService is a simple format converter; E2E tests cover the happy path |

---

## Files to trim (−15 tests)

### `tests/Unit/LoginServiceTest.php` 4 → 1 (−3)

Keep only `testSkipsLoginWhenCookiesFileExists` — the only test that covers a real branching decision. The other 3 all test the same empty-credentials path.

### `tests/Unit/EnvironmentServiceTest.php` 13 → 8 (−5)

**Keep** — these test the core merging behavior with real assertions:
- `testProfileOverridesBaseSettings` — profile URL wins over base URL
- `testEnvironmentOverridesProfileSettings` — strengthen: assert `INVRT_URL` is the env URL, not just env name
- `testEnvironmentCredentialsOverrideProfile` — strengthen: assert `INVRT_USERNAME === 'env_user'`
- `testDeviceOptionStoredInEnvironment` — device is stored
- `testDataDirectoryPathConstruction` — path contains profile/env/data
- `testConfigFilePathConstruction` — path contains config.yaml
- `testCookiesFilePathConstruction` — path contains profile/env
- `testProfileCredentialsExtraction` — strengthen: assert `INVRT_USERNAME === 'admin_user'`

**Delete** — trivial or duplicate:
- `testDefaultProfileSelection` — asserts value equals what you passed in
- `testCustomProfileSelection` — same
- `testEnvironmentSpecificUrl` — asserts env name, not the URL
- `testEnvironmentCredentialsExtraction` — asserts env name, not the credentials
- `testProfileDeviceEnvironmentCombination` — fully covered by the above individual tests
- `testComplexConfigNesting` — just re-asserts the 3 values you passed in

### `tests/E2E/ConfigCommandTest.php` 9 → 3 (−6, +1 new = −5 net)

"Simplify the config handling." The current tests mostly just assert `assertCommandSuccess()`.

**Keep:**
- `testConfigCommandWithoutConfigFile` — distinct behavior (graceful no-config path)

**Replace the rest with 3 focused tests:**
- `testConfigCommandDisplaysResolvedValues` — run with profile+environment+device, assert the resolved URL appears in output
- `testConfigCommandWithInvalidYaml` — strengthen: assert an error message is shown (not `assertTrue(true)`)
- Remove the 3 individual option tests (`--profile`, `--environment`, `--device`) and the bare success test

---

## Net result

| | Before | After |
|-|--------|-------|
| Total tests | 77 | ~30 |
| Deleted | — | −32 (3 full files) |
| Trimmed | — | −14 (from 3 files) |
| Strengthened | — | 4 EnvironmentService + 2 ConfigCommand assertions improved |

---

## Implementation steps

1. Delete `tests/Unit/InvrtCliTest.php`
2. Delete `tests/Unit/ErrorHandlingTest.php`
3. Delete `tests/Unit/CookieServiceTest.php`
4. Trim `tests/Unit/LoginServiceTest.php` to 1 test
5. Trim and strengthen `tests/Unit/EnvironmentServiceTest.php` (13 → 8)
6. Rewrite `tests/E2E/ConfigCommandTest.php` (9 → 3)
7. Run `task test` — all remaining tests pass
8. Mark TODO.md item as done
