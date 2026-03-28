# Plan: Improve E2E `CrawlCommandTest`

## Requirement (from TODO)

> Improve `tests/E2E/CrawlCommandTest.php`.
> Use the same fixture website used by `ReferenceCommandTest` and `TestCommandTest`.
> Expand the fixture website to include 5 pages to crawl.

## Current State

- `tests/E2E/CrawlCommandTest.php` currently has weak assertions (`assertTrue(true)`) and mostly checks command invocation.
- It does not verify real crawl outcomes (`crawled_urls.txt`, URL count, discovered paths).
- `ReferenceCommandTest` and `TestCommandTest` already use `WebCommandTestCase` and the shared website fixture.
- The shared fixture site currently has 2 pages (`index.html`, `about.html`).

## Target Outcome

- `CrawlCommandTest` performs real end-to-end verification against the same built-in PHP fixture webserver pattern used by other E2E web command tests.
- Fixture website contains 5 deterministic, linked pages.
- Crawl test verifies discovered URL paths and output behavior, not just command startup.

## Implementation Plan

1. Expand the fixture website to 5 pages.
   - Keep existing `tests/fixtures/website/index.html` and `tests/fixtures/website/about.html`.
   - Add 3 additional stable pages:
     - `tests/fixtures/website/services.html`
     - `tests/fixtures/website/contact.html`
     - `tests/fixtures/website/team.html`
   - Ensure pages are linked from `index.html` (and optionally cross-linked) so wget can discover all 5 paths.
   - Keep markup simple and deterministic for stable E2E behavior.

2. Refactor `tests/E2E/CrawlCommandTest.php` to use `WebCommandTestCase`.
   - Replace direct `CommandTestCase` inheritance with `WebCommandTestCase`.
   - Remove manual `chdir()` / `INIT_CWD` handling and rely on existing fixture setup conventions.

3. Configure crawl tests against the shared local webserver URL.
   - In each crawl test setup, write config with:
     - `environments.local.url = $this->webserverUrl()`
     - sensible crawl settings (`max_crawl_depth`, `max_pages`) where needed.
   - Use the same fixture website as reference/test E2E tests.

4. Add strong crawl assertions.
   - Run `crawl` and assert command success.
   - Assert `.invrt/data/anonymous/local/crawled_urls.txt` exists.
   - Assert file contains expected paths (sorted unique set):
     - `/`
     - `/about.html`
     - `/contact.html`
     - `/services.html`
     - `/team.html`
   - Assert exactly 5 unique paths were discovered.
   - Assert verbose output contains crawl status line and the local webserver URL.

5. Keep/adjust config-required failure test.
   - Preserve the missing-config behavior test (`Configuration file not found`).
   - Ensure this remains a focused negative-path E2E test.

6. Validate with targeted and full test runs.
   - Run targeted tests first:
     - `vendor/bin/phpunit tests/E2E/CrawlCommandTest.php`
   - Run full suite/lint command required by project:
     - `task test`

7. Update TODO when complete.
   - Mark `Improve e2e tests/E2E/CrawlCommandTest.php` as done in `TODO.md` only after tests pass.

## Notes / Risks

- Crawl behavior depends on `wget` log parsing in `CrawlCommand::parseUrlsFromLog()`, so assertions should focus on path outcomes, not exact wget stderr formatting.
- If environment-specific differences affect discovery order, compare normalized sets (unique + sorted) rather than raw line order.