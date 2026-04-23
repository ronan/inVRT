# Plan: Move Check and Crawl Logic to JavaScript

## Goal

Slim down `Runner.php` by moving the business logic for the `check` and `crawl` operations to dedicated Node.js scripts. PHP retains orchestration (prerequisites, exit codes, env export) and config management; JS handles the actual work.

## Approach

Create two new JS scripts — `check.js` and `crawl.js` — that handle what PHP currently does internally. Update `Runner.php` to call these scripts instead. Remove the PHP private helpers that are no longer needed.

The `backstop-config.js` is refactored to export its logic so `crawl.js` can require and call it directly, eliminating an extra subprocess.

## Files Changed

### New

- `src/js/check.js` — site check logic  
- `src/js/crawl.js` — crawl orchestration and URL parsing

### Modified

- `src/js/backstop-config.js` — export `generateBackstopConfig()` function; entry point unchanged
- `src/core/Runner.php` — slim down `check()` and `crawl()`; remove unused private helpers
- `tests/Unit/CrawlCommandTest.php` — remove `parseUrlsFromLog` unit tests (behaviour covered by bats E2E)

---

## `src/js/check.js`

Reads env vars: `INVRT_URL`, `INVRT_CHECK_FILE`, `INVRT_USER_AGENT`

Steps:
1. Make a `HEAD` request without following redirects to detect if the first response is a 301.
2. Make a `GET` request following redirects to get the final URL, HTML body, and HTTP status.
3. Extract page title from the HTML `<title>` tag.
4. Detect HTTPS from the final URL scheme.
5. Write `check.yaml` using `js-yaml` with keys: `url`, `title`, `https`, `redirected_from` (if 301), `checked_at`.
6. Log via pino (`logger.js`). Exit 0 on success, 1 on failure.

Use Node built-in `https`/`http` modules (no new dependencies). Use `js-yaml` (already a dependency) for writing.

---

## `src/js/crawl.js`

Reads env vars: `INVRT_URL`, `INVRT_CRAWL_FILE`, `INVRT_CRAWL_LOG`, `INVRT_CLONE_DIR`, `INVRT_MAX_CRAWL_DEPTH`, `INVRT_MAX_PAGES`, `INVRT_EXCLUDE_FILE`, `INVRT_COOKIE`, `INVRT_COOKIES_FILE`, `INVRT_PROFILE`, `INVRT_ENVIRONMENT`, `INVRT_CHECK_FILE`

Steps:
1. Clear the crawl log and crawl file if they exist.
2. Build wget args (mirrors PHP logic):
   - `resolveExcludeArg()` — reads `INVRT_EXCLUDE_FILE`, falls back to `/user/*`
   - `resolveCookieArg()` — uses `INVRT_COOKIE` header or loads cookie `.txt` file
3. Log the crawl start (`environment`, `url`, `profile`, `maxDepth`, `maxPages`).
4. Run site check if `INVRT_CHECK_FILE` does not exist (spawns `check.js`).
5. Spawn `wget` with the built args, stderr redirected to `INVRT_CRAWL_LOG`.
6. Parse the crawl log: extract paths from lines matching `URL:<baseUrl><path>`, deduplicate, sort.
7. Write paths to `INVRT_CRAWL_FILE` (one per line).
8. If no paths found, log tail of crawl log and exit 1.
9. Call `generateBackstopConfig()` from `backstop-config.js` (required, not spawned).
10. Exit 0 on success, 1 on failure.

---

## `src/js/backstop-config.js` changes

Wrap existing logic in an exported function:

```js
const generateBackstopConfig = () => { /* existing logic */ };
module.exports = { generateBackstopConfig };
if (require.main === module) { generateBackstopConfig(); }
```

---

## `src/core/Runner.php` changes

### `check()` — becomes thin wrapper

```php
public function check(): int
{
    return $this->runNode('check.js');
}
```

### `crawl()` — becomes thin wrapper

```php
public function crawl(): int
{
    // Log the crawl start (environment/url/profile context)
    $env = $this->config->all();
    $this->logger->info("🕸️ ...");
    // Prepare directories (captureDir, cloneDir) — still in PHP since it requires
    // the filesystem state to be clean before the node process runs
    $this->prepareDirectory($env['INVRT_CRAWL_DIR'] ?? '');
    return $this->runNode('crawl.js');
}
```

### New helper: `runNode(string $script): int`

Replaces the repeated pattern of `Process::fromShellCommandline('node ' . escapeshellarg($script))` + `NodeOutputParser`. Used by `check()`, `crawl()`, and `generateBackstopConfig()`.

### Removed private methods

- `extractTitle()`
- `getInitialHttpCode()`
- `resolveCookieArg()`
- `resolveExcludeArg()`
- `logCrawlLogTail()`
- `parseUrlsFromLog()` (was public static — now lives in `crawl.js`)

---

## `tests/Unit/CrawlCommandTest.php` changes

Remove the class (or all test methods). The URL parsing behaviour is fully covered by `workflow.bats` E2E tests which assert that crawl file contents are correct after running the real command.

---

## What does NOT change

- `init()` — config file creation stays in PHP
- `info()` — file/directory querying stays in PHP
- `reference()`, `test()`, `approve()`, `baseline()` — already thin wrappers around `backstop.js`; prerequisite chains stay in PHP
- `login()` — already delegates to `LoginService` → `playwright-login.js`
- All bats E2E tests — no changes needed

---

## Testing

After implementation, run `task test`. The bats workflow tests cover check and crawl end-to-end. No new tests are required; the removed PHP unit tests are replaced by the existing E2E coverage.
