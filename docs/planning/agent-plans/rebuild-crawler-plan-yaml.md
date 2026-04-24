# Plan: Rebuild Crawl Using Playwright + plan.yaml

## Goal

Replace the wget-based crawler with a Playwright-based crawler that:

1. Starts from paths listed in `.invrt/plan.yaml` (initially `/` from init/check).
2. Visits HTML pages, extracts links, and continues crawling discovered in-scope paths.
3. Updates `plan.yaml` with discovered paths.
4. Preserves user-defined plan keys while adding crawler-managed metadata.
5. Keeps writing newline-delimited paths to `INVRT_CRAWL_FILE` for current downstream commands.

## Scope (this implementation)

- Rewrite `src/js/crawl.js` to use Playwright.
- Read/write plan data from `INVRT_PLAN_FILE`.
- Respect `INVRT_MAX_PAGES`, `INVRT_MAX_CRAWL_DEPTH`, and `INVRT_EXCLUDE_FILE`.
- Add/update `profiles` array on each discovered page to indicate profile access.
- Keep `crawl` command behavior and outputs compatible with existing workflow.

## Implementation Steps

1. Docs-first updates
- Update `docs/user/en/usage.md` crawl section to describe Playwright crawl + plan updates.
- Update `docs/spec/APP_SUMMARY.md` crawl behavior from wget log parsing to Playwright crawling.

2. Rewrite crawler
- Replace wget spawn logic in `src/js/crawl.js` with Playwright Chromium crawl:
  - Seed queue from `plan.yaml` `pages` keys; fallback to `/`.
  - Visit pages breadth-first.
  - Only add URLs on same origin.
  - Treat only `text/html` responses as crawlable pages.
  - Scrape `a[href]` links and enqueue normalized in-scope paths.
- Keep logs in `INVRT_CRAWL_LOG`.
- Emit sorted crawled paths to stdout (for `INVRT_CRAWL_FILE`).

3. plan.yaml merge behavior in JS crawler
- Load existing `plan.yaml` (if missing, create minimal root structure).
- For each discovered path:
  - Ensure a page entry exists.
  - Ensure `profiles` contains current `INVRT_PROFILE`.
  - Preserve existing metadata keys and values.
- Persist updated plan file at end of crawl.

4. Tests
- Update/add Bats workflow assertions:
  - crawl still writes expected paths to `crawled-paths.text`.
  - crawl updates `plan.yaml` with discovered paths.
  - crawl adds/merges `profiles` for discovered pages.

5. Validation
- Run `task test`.

## Acceptance Criteria

- `invrt crawl` no longer depends on wget output parsing.
- `invrt crawl` updates `.invrt/plan.yaml` with discovered in-scope HTML paths.
- Existing user fields in plan entries remain intact.
- Current pipeline still receives `crawled-paths.text` and tests pass.
