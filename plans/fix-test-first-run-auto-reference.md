# Plan: Fix First-Run `invrt test` Auto-Reference Flow

## Problem

Running `invrt test` in a freshly initialized project can fail because `.invrt/.../crawled_urls.txt`
does not exist yet. The current `Runner::test()` method auto-triggers Backstop `reference` directly,
which bypasses `Runner::reference()` logic that auto-runs crawl and validates crawled URLs.

## Goal

Make first-run `invrt test` reliably work by using the same guarded flow as `invrt reference`:
auto-crawl when needed, validate crawled URLs, then capture references, then run tests.

## Changes

1. Update `core/src/Runner.php`
   - In `Runner::test()`, when references are missing, call `$this->reference()` instead of
     `runBackstop('reference', $env)`.
   - Keep the existing notice message for missing references.

2. Add/adjust E2E coverage
   - Add a test in `tests/E2E/TestCommandTest.php` for first-run behavior with no
     pre-existing crawl file and no reference bitmaps.
   - Assert that `test` succeeds and output includes both:
     - `📸 No reference screenshots found — capturing references first.`
     - `🕸️ No crawled URLs found — running crawl first.`

3. Update docs and TODO
   - Ensure `docs/usage.md` documents that first-run `invrt test` auto-captures references
     (and may trigger crawl via reference prerequisites).
   - Mark the TODO item complete in `TODO.md` if tests pass.

## Validation

- Run targeted tests:
  - `php vendor/bin/phpunit --configuration=tooling/phpunit.xml --filter TestCommandTest tests/E2E/TestCommandTest.php`
  - `php vendor/bin/phpunit --configuration=tooling/phpunit.xml --filter ReferenceCommandTest tests/E2E/ReferenceCommandTest.php`
- Optionally verify manually in a temp project:
  - `invrt init`
  - `invrt test`
