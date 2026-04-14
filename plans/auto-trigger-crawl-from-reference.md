# Plan: Auto Trigger `invrt crawl` When `invrt reference` is Run for the First Time

## Problem

When `invrt reference` is run in a new branch/project (before any crawl has happened), it fails
with "No crawled URLs file found. Run `invrt crawl` first." Users have to know the order of
operations. The tool should detect the missing crawl file and run crawl automatically.

## Behaviour

- If `crawled_urls.txt` does **not exist** → auto-trigger `invrt crawl`, then continue with reference.
- If `crawled_urls.txt` **exists but is empty** → error: crawl ran but found no usable URLs (existing behaviour).
- If `crawled_urls.txt` **exists with URLs** → proceed as normal (existing behaviour).

## Approach

Inject `CrawlCommand` into `ReferenceCommand` via constructor DI. When the crawl file is
absent, call `$this->crawlCommand->__invoke($io, $opts)` with the same options before
proceeding. If the crawl fails, surface the failure and return early.

The DI container in `src/invrt.php` uses `autowire`, so adding the CrawlCommand parameter to
ReferenceCommand's constructor is enough for production wiring.

## Steps

1. **Update `docs/usage.md`** — document the auto-crawl behaviour under the `reference` section.

2. **Update `ReferenceCommand.php`**:
   - Add `private CrawlCommand $crawlCommand` constructor parameter.
   - In `__invoke()`, before `validateCrawledUrls()`, check if the crawl file is absent.
   - If absent, log an informational message and call `$this->crawlCommand->__invoke($io, $opts)`.
   - If the crawl fails, return early with its exit code.
   - `validateCrawledUrls()` retains its check for an empty crawl file (crawl ran, nothing found).

3. **Update `tests/E2E/CommandTestCase.php`** — pass a `CrawlCommand` instance to `ReferenceCommand`'s constructor when creating the test application.

4. **Update `tests/E2E/ReferenceCommandTest.php`**:
   - Update `testReferenceCommandCapturesScreenshots` to remove the manual `writeCrawledUrlsFile` call; the auto-trigger should handle it. Verify crawl output appears in the output.
   - Update `testReferenceCommandShowsDebugOutputAtVvv` similarly.
   - Add a new test `testReferenceAutoTriggersCrawlWhenNoCrawlFileExists` that runs reference with no prior crawl and asserts both crawl output and reference screenshots are produced.

5. **Update `TODO.md`** — mark the item as done (`[x]`).
