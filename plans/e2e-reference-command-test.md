# Plan: E2E: ReferenceCommandTest (and TestCommandTest)

## Requirement

> The full workflow must run and create and verify the existence and accuracy of screenshots taken during the reference run. Before the test starts a webserver using the built-in PHP webserver that serves a simple website that the test can use to crawl and capture.

## What the reference command does

1. `EnvironmentService::initialize()` — sets `INVRT_URL`, `INVRT_DATA_DIR`, `INVRT_SCRIPTS_DIR`, etc.
2. `handleLogin()` — skipped (no credentials in test config)
3. Runs `node src/backstop.js reference` via Symfony Process, passing `$env`

`backstop.js` reads:
- `INVRT_DATA_DIR` — data directory root
- `INVRT_URL` — base URL for screenshots
- `{INVRT_DATA_DIR}/crawled_urls.txt` — list of URL paths, one per line

It writes reference screenshots to `{INVRT_DATA_DIR}/bitmaps/reference/`.

`playwright-onbefore.js` loads `{INVRT_DATA_DIR}/cookies.json` only if the file exists (optional — safe to omit).

## Config structure — important

`EnvironmentService` resolves `INVRT_URL` from `environments.{env}`, `profiles.{profile}`, or `devices.{device}` sections **only**. The top-level `project.url` key is **not read**. The test config must include an `environments.local` block with the webserver URL:

```yaml
environments:
  local:
    url: http://127.0.0.1:{port}
```

## Test website fixture

`tests/fixtures/website/` serves two pages:

- `index.html` — homepage (`<title>Home</title>`)
- `about.html` — second page (`<title>About</title>`)

Two pages means `crawled_urls.txt` has `/` and `/about.html`, exercising multi-scenario handling in backstop.js. Content must be stable and simple so screenshots are deterministic across runs.

## "Existence and accuracy" defined

- **Existence**: `bitmaps/reference/` directory is created and contains at least one `.png` per URL in `crawled_urls.txt`
- **Accuracy**: each PNG file has a non-zero file size (i.e. Playwright actually wrote pixel data, not an empty placeholder)

Pixel-perfect screenshot comparison is the job of the `test` command — not what we verify here.

## Test Infrastructure

### PHP built-in webserver
- Start **once per test class** using `setUpBeforeClass()`, stop in `tearDownAfterClass()`
- Serves from `tests/fixtures/website/` (fixed path — independent of per-test fixture temp dirs)
- Find a free port dynamically: `stream_socket_server('tcp://127.0.0.1:0')`
- Wait for readiness by polling the port (up to 3s) before proceeding

### Scratch output directory
`TestProjectFixture` defaults to `scratch/tmp/` instead of `sys_get_temp_dir()`. This applies to **all** E2E tests — no per-class overrides needed. PNGs and other artefacts are always inspectable in `scratch/tmp/` after a run. Don't clean up after tests. Don't randomize the output directory. Don't let files collide between tests but you can overright between runs. The `scratch/tmp/` should be considered an artifact of the tests.

### TestProjectFixture change
Change default base dir from `sys_get_temp_dir() . '/invrt_test_' . uniqid()` to `{project_root}/scratch/tmp/{test-name}`. The `$baseDir` constructor param already exists — no new API needed.

## Test cases: ReferenceCommandTest

**File:** `tests/E2E/ReferenceCommandTest.php`

### setUp

1. Call `parent::setUp()` (creates fixture, sets `INVRT_DIRECTORY`)
2. Write config with `environments.local.url` pointing to webserver
3. Write `crawled_urls.txt` with entries: `/` and `/about.html`

### testRequiresConfig
- No config written
- Execute `reference`, expect `RuntimeException` "Configuration file not found"

### testReferenceCommandCapturesScreenshots
1. Execute `reference` command
2. Assert exit code 0
3. Assert `{INVRT_DATA_DIR}/bitmaps/reference/` directory exists
4. Assert at least one `.png` file per URL (2 PNGs total)
5. Assert each PNG has a non-zero file size

### testReferenceCommandOutputContainsStatusLine
1. Execute `reference` with `VERBOSITY_VERBOSE`
2. Assert output contains `📸 Capturing references`
3. Assert output contains the webserver URL

## Test cases: TestCommandTest

`TestCommandTest` follows the same webserver + fixture pattern. It runs **after** the reference screenshots exist (i.e. run `reference` in `setUp` to seed them, then run `test`).

**File:** `tests/E2E/TestCommandTest.php`

### setUp

Same as `ReferenceCommandTest::setUp()`. Additionally, run `reference` command first to create the bitmaps that `test` will compare against.

### testRequiresConfig
- Same pattern as ReferenceCommandTest.

### testTestCommandRunsComparison
1. Execute `test` command
2. Assert exit code 0 (no visual regressions since reference and test are identical)
3. Assert `{INVRT_DATA_DIR}/bitmaps/test/` directory exists and contains PNGs

### testTestCommandOutputContainsStatusLine
1. Execute `test` with `VERBOSITY_VERBOSE`
2. Assert output contains `🔬 Testing`
3. Assert output contains the webserver URL

## Files Changed

| File | Change |
|---|---|
| `tests/fixtures/website/index.html` | **New** — homepage (`<title>Home</title>`) |
| `tests/fixtures/website/about.html` | **New** — second page (`<title>About</title>`) |
| `tests/fixtures/TestProjectFixture.php` | Change default base dir to `scratch/tmp/invrt_test_{uniqid}`; add `writeCrawledUrlsFile()` method |
| `tests/E2E/ReferenceCommandTest.php` | **New** |
| `tests/E2E/TestCommandTest.php` | **New** |
| `TODO.md` | Mark both E2E items `[x]` |

## Order of Work

1. Create `tests/fixtures/website/index.html` and `about.html`
2. Add `writeCrawledUrlsFile()` to `TestProjectFixture`
3. Write `ReferenceCommandTest` — run and pass
4. Write `TestCommandTest` — run and pass
5. Run `task test` — all green
6. Mark both `TODO.md` items done

