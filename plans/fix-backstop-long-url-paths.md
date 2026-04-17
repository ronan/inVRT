# Plan: Fix Backstop failures caused by long URL-derived file paths

## Goal
Prevent `invrt reference` and `invrt test` from failing when crawled URLs are very long and BackstopJS generates bitmap/report file paths that exceed filesystem limits.

## Scope
- Update scenario construction in `src/backstop.js` to stop using raw URL strings as scenario labels.
- Use a deterministic, short scenario identifier that remains stable across runs.
- Preserve useful context for debugging by keeping the full URL in scenario metadata/log output.
- Add automated test coverage for long URL entries in `crawled_urls.txt`.
- Update any related agent summary docs only if behavior changes are user-facing.

## Proposed Implementation
1. Add a helper in `src/backstop.js` to create a compact scenario label from the URL path:
   - deterministic across runs
   - ASCII-safe
   - bounded length
   - collision-resistant (include short hash suffix)
2. Build each scenario with:
   - `label`: compact identifier
   - `url`: full URL (`INVRT_URL + path`)
   - optional descriptive field for debugging (for example `referenceUrl`/`_invrtPath`) if supported safely
3. Filter out empty lines from `crawled_urls.txt` while building scenarios.
4. Add/extend tests to verify long crawled paths no longer break the command flow:
   - create a very long path in crawl input
   - run reference/test command
   - assert successful command completion and expected bitmap output
5. Run `task test` and fix any regressions.
6. Mark the todo item as complete in `TODO.md` once tests pass.

## Validation
- Reproduce with a long URL entry and confirm `invrt reference` succeeds.
- Confirm generated bitmap/report files are created under expected directories.
- Confirm no existing test behavior regresses.

## Risks and Mitigations
- Risk: hash collisions for compact labels.
  - Mitigation: include both a normalized prefix and hash suffix.
- Risk: changing labels affects report readability.
  - Mitigation: keep full URL in scenario URL and log context; choose readable compact label format.
