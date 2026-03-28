# Plan: Improve CrawlCommandTest E2E Tests

## Problem

`tests/E2E/CrawlCommandTest.php` is weak. Most tests just assert `assertTrue(true, ...)` — they don't verify any real behaviour. The crawl command actually uses `wget` to crawl a website, parses the log, and writes `crawled_urls.txt`. None of that is tested.

The existing `ReferenceCommandTest` and `TestCommandTest` use a pattern (`WebCommandTestCase`) that spins up a real PHP webserver from `tests/fixtures/website/`. `CrawlCommandTest` should use the same pattern and actually run crawls against that webserver.

The fixture website has only 2 pages. The TODO asks to expand it to 5 pages so the crawl tests have more interesting data.

## Approach

1. **Expand the fixture website** (`tests/fixtures/website/`) from 2 to 5 HTML pages, with links between them so `wget` can crawl them recursively.

2. **Rewrite `CrawlCommandTest`** to extend `WebCommandTestCase` instead of `CommandTestCase`, replacing the placeholder assertions with real behavioral tests.

## Steps

### 1. Expand fixture website to 5 pages

Add `services.html`, `contact.html`, and `blog.html` to `tests/fixtures/website/`.

Update `index.html` and `about.html` to link to the new pages so wget can discover them by following links.

Pages:
- `index.html` — links to about, services, contact, blog
- `about.html` — links to home
- `services.html` — links to home, contact
- `contact.html` — links to home
- `blog.html` — links to home

### 2. Rewrite CrawlCommandTest

Extend `WebCommandTestCase`. Remove the manual `chdir`/`putenv` boilerplate (handled by the base class).

Tests to implement:

- **`testRequiresConfig`** — no config → `RuntimeException` (matching existing reference/test pattern)
- **`testCrawlDiscoversAllPages`** — run real crawl against webserver; assert `crawled_urls.txt` exists and contains all 5 page paths
- **`testCrawlOutputContainsStatusLine`** — verbose mode; assert output contains the crawling status message and the URL
- **`testCrawlWithEnvironmentOption`** — crawl with `--environment local`; assert success and URLs file created
- **`testCrawlCreatesLogFile`** — after crawl, assert `crawl.log` was created in data dir

### 3. Update WebCommandTestCase.setupFixture (if needed)

`setupFixture()` currently writes a `crawled_urls.txt` with only 2 paths. The crawl test doesn't need pre-seeded URLs — it will generate them. Each test that needs the fixture can call `$this->fixture->writeConfig(...)` directly (just the env config, no URL seeding).

A new helper method `setupCrawlFixture()` can be added to `WebCommandTestCase` or handled inline in `CrawlCommandTest`.

### 4. Check off the TODO item

Mark the `CrawlCommandTest` item as done in `TODO.md`.
