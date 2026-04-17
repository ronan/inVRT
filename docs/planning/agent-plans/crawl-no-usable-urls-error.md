# Plan: Crawl No-URL Failure With Sentinel File

## Source todo item

- [.] Return error when `invrt crawl` finds no usable urls.
   - Show the last 5 lines of the crawl.log
   - Create an empty crawled_urls.txt file to indicate crawl has run and failed so reference does not trigger crawl again

## Goal

Make `invrt crawl` fail clearly when crawling yields zero usable URLs, provide actionable debugging context, and create a sentinel `crawled_urls.txt` file (empty) to represent "crawl attempted but found nothing".

## Current behavior to verify

1. Confirm how `crawled_urls.txt` is currently created in the crawl flow (`CrawlCommand` and invoked scripts).
2. Confirm current exit code and console output when crawl output is empty.
3. Confirm where `crawl.log` is written and how to safely read its tail.
4. Confirm how `reference` decides whether to auto-trigger `crawl`, and whether file existence is the only trigger condition.

## Planned behavior

1. Detect the zero-usable-URL condition after crawl processing.
2. Write an empty `crawled_urls.txt` file in that condition as a sentinel that crawl already ran.
3. Return `Command::FAILURE` from `invrt crawl` when no usable URLs are found.
4. Print a concise error message that includes:
   - A statement that no usable URLs were found.
   - The last 5 lines of `crawl.log` (if present).
5. If `crawl.log` is missing or unreadable, show a short fallback message and still fail.

## Implementation steps

1. Locate URL filtering/output write path in:
   - `src/Commands/CrawlCommand.php`
   - related crawl shell scripts in `src/`.
2. Add a guard for empty usable URL list after parse.
3. On empty result, write `crawled_urls.txt` as an empty file.
4. Add helper logic (command-local or shared utility) to read and display the last 5 lines of `crawl.log` with proper verbosity level usage for `$io->writeln()` calls.
5. Ensure command exits with explicit failure code in this condition.
6. Ensure success path behavior is unchanged (non-empty URL file with newline-terminated entries).
7. Verify `reference` behavior against the sentinel file contract; if needed, add/adjust guard logic so reference returns a clear error for an empty file instead of auto-crawling.

## Test plan

### E2E tests

1. Add/extend `tests/e2e/CrawlCommandTest.php`:
   - Scenario where crawl completes but produces no usable URLs.
   - Assert failure exit code.
   - Assert output contains no-usable-URL error.
   - Assert output includes expected log tail lines (or fallback message if log absent).
   - Assert `crawled_urls.txt` exists and is empty.
2. Keep existing happy-path scenario asserting normal file creation and success.
3. Add/extend `tests/e2e/ReferenceCommandTest.php`:
   - Seed an empty `crawled_urls.txt` and run `reference`.
   - Assert reference returns a clear no-urls error and does not auto-trigger crawl.

### Unit tests (only if needed)

1. Add unit coverage only for isolated parsing/tail logic if extracted into a dedicated service/helper.

## Documentation updates

1. Update usage docs in `docs/usage.md` for `invrt crawl` failure semantics when no URLs are found.
2. Document that an empty `crawled_urls.txt` is intentionally created in this case.
3. Add a short example of the emitted error and log tail output.

## Acceptance criteria

1. Running `invrt crawl` with zero usable URLs exits non-zero.
2. Output clearly explains the failure and prints the last 5 `crawl.log` lines (or a fallback note).
3. An empty `crawled_urls.txt` is created in the no-URL failure path.
4. `invrt reference` treats an empty `crawled_urls.txt` as "crawl already ran but no URLs found" and returns a clear error without auto-crawling.
5. Existing crawl success flows continue to pass.
6. `task test` passes.

## Out of scope

1. Changes to URL filtering rules themselves.
2. Refactoring broader crawl/report orchestration beyond this specific failure mode.
