# Plan: Return Error When `invrt crawl` Finds No Usable URLs

## Source todo item

- [ ] Return error when `invrt crawl` finds no usable urls.
  - Show the last 5 lines of the crawl.log
  - Don't create an empty crawled_urls.txt file

## Goal

Make `invrt crawl` fail clearly when crawling yields zero usable URLs, provide actionable debugging context, and avoid creating misleading empty output artifacts.

## Current behavior to verify

1. Confirm how `crawled_urls.txt` is currently created in the crawl flow (`CrawlCommand` and invoked scripts).
2. Confirm current exit code and console output when crawl output is empty.
3. Confirm where `crawl.log` is written and how to safely read its tail.

## Planned behavior

1. Detect the zero-usable-URL condition after crawl processing and before writing final URL output.
2. Return `Command::FAILURE` from `invrt crawl` when no usable URLs are found.
3. Print a concise error message that includes:
   - A statement that no usable URLs were found.
   - The last 5 lines of `crawl.log` (if present).
4. Do not create `crawled_urls.txt` when there are no usable URLs.
5. If `crawl.log` is missing or unreadable, show a short fallback message and still fail.

## Implementation steps

1. Locate URL filtering/output write path in:
   - `src/Commands/CrawlCommand.php`
   - related crawl shell scripts in `src/`.
2. Add a guard for empty usable URL list before output file creation.
3. Add helper logic (command-local or shared utility) to read and display the last 5 lines of `crawl.log` with proper verbosity level usage for `$io->writeln()` calls.
4. Ensure command exits with explicit failure code in this condition.
5. Ensure success path behavior is unchanged.

## Test plan

### E2E tests

1. Add/extend `tests/e2e/CrawlCommandTest.php`:
   - Scenario where crawl completes but produces no usable URLs.
   - Assert failure exit code.
   - Assert output contains no-usable-URL error.
   - Assert output includes expected log tail lines (or fallback message if log absent).
   - Assert `crawled_urls.txt` does not exist.
2. Keep existing happy-path scenario asserting normal file creation and success.

### Unit tests (only if needed)

1. Add unit coverage only for isolated parsing/tail logic if extracted into a dedicated service/helper.

## Documentation updates

1. Update usage docs in `docs/usage.md` for `invrt crawl` failure semantics when no URLs are found.
2. Add a short example of the emitted error and log tail output.

## Acceptance criteria

1. Running `invrt crawl` with zero usable URLs exits non-zero.
2. Output clearly explains the failure and prints the last 5 `crawl.log` lines (or a fallback note).
3. No empty `crawled_urls.txt` is created.
4. Existing crawl success flows continue to pass.
5. `task test` passes.

## Out of scope

1. Changes to URL filtering rules themselves.
2. Refactoring broader crawl/report orchestration beyond this specific failure mode.
