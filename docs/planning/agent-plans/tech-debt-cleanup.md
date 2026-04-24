# Tech Debt Cleanup Plan

Addresses the open **Tech Debt** items in `TODO.md`:

1. Refactor runner to only contain public functions (move helpers to external includes; abstract directory/file handling).
2. Use `INVRT_PLAN_FILE` instead of `INVRT_CRAWL_FILE` where appropriate.
3. Remove `array $env` from `runPlaywright()` and use `$this->config` directly.
4. Remove config validation and defaults from the Runner. Config handler must provide sane defaults; Runner trusts them.
5. Remove more business logic from `Runner.php`:
   - Implement `info` as `src/js/info.js` (drop crawl-log tail).
   - Implement `configurePlaywright` as `src/js/configure-playwright.js`.

## Scope

Only the tech-debt items above. No feature additions, no doc rewrites beyond what the changes require, no test-strategy changes.

## Plan

### A. Runner shape — public commands only

Target: `src/core/Runner.php` contains only:
- Public command methods (`init`, `info`, `check`, `crawl`, `reference`, `test`, `approve`, `baseline`, `configureBackstop`, `configurePlaywright`, `generatePlaywright`, `login`, `getConfig`).
- Constructor.
- One `runNode()` dispatcher method (kept private — it's the glue that is Runner's job).

Extract everything else to small, focused collaborators under `src/core/Service/`:

- `Service\PlaywrightRunner` — wraps the playwright subprocess. Takes `Configuration` + `LoggerInterface`. Exposes `run(string $mode): int`. Absorbs current private `runPlaywright()` and `writeResultsFile()`.
- `Service\ProjectId` — tiny static helper holding `generate()` and `encode()` (moves `generateProjectId`/`encodeId`). Keep thin.
- `Service\UrlNormalizer` — static `normalize(string $url): string` (moves `normalizeURL`).
- Existing `Service\PlanService`, `Service\LoginService`, `Service\CookieService`, `Service\NodeOutputParser` unchanged.

Private helpers `validateCrawledUrls()`, `referencesAreMissing()`, `countScreenshots()`, `readLogTail()` either:
- Disappear (the first two are replaced by plan-file checks — see §B; `countScreenshots` and `readLogTail` leave PHP entirely in §E).

- `Service\Filesystem` — small static helper wrapping the two filesystem idioms Runner uses repeatedly: `ensureDir(string $path): void` (create if missing) and `writeFile(string $path, string $contents): void` (ensure parent dir, then write, throw on failure). Used by `init()` and any remaining file writes.

### B. `INVRT_PLAN_FILE` vs `INVRT_CRAWL_FILE`

`plan.yaml` is the canonical source of truth (populated by `crawl.js`). `crawled-paths.text` is a legacy flat listing.

Switch these callers from crawl file → plan file:
- `Runner::reference()` prerequisite check — check `INVRT_PLAN_FILE` exists (and has `pages`) instead of `INVRT_CRAWL_FILE`.
- `Runner::validateCrawledUrls()` (removed): replaced by a plan-file check that asks `PlanService` whether the plan has at least one page. If not, emit the existing "no usable URLs" message.
- `Runner::configureBackstop()` input — pipe `INVRT_PLAN_FILE` to `backstop-config.js`. Update `src/js/backstop-config.js` to parse YAML `pages[]` for scenarios instead of newline-delimited paths.
- `Runner::info()` — report page count from plan file instead of crawl file (moves to JS in §E).

Keep `INVRT_CRAWL_FILE` as the **output** of `Runner::crawl()` (`crawl.js` still writes it). Do not remove the env var — several callers and tests still rely on it. Just stop reading it as input anywhere except `crawl.js`'s own output path.

### C. Remove `array $env` from `runPlaywright()`

`runPlaywright(string $mode, array $env)` → `runPlaywright(string $mode)`. Read everything from `$this->config`. Callers (`reference`, `test`, `approve`, `baseline`) stop building `$env` for this purpose. When moved into `PlaywrightRunner` (§A) it takes `Configuration` in its constructor.

### D. Trust the config handler

Runner-side cleanup:
- Delete defensive defaults like `$env['INVRT_DEVICE'] ?? 'desktop'`, `'anonymous'`, `'local'`, `'1024'`. Use `$this->config->get('INVRT_DEVICE')` (no default).
- Delete per-command "is not set" guards for `INVRT_PLAN_FILE`, `INVRT_PLAYWRIGHT_CONFIG_FILE`, `INVRT_PLAYWRIGHT_SPEC_FILE`, `INVRT_BACKSTOP_CONFIG_FILE`, `INVRT_CRAWL_FILE`, etc. These keys all have defaults in `ConfigSchema::DEFAULTS`; guarding for empties masks config bugs rather than fixing them.
- Leave genuine input validation in place (e.g., `init()` requiring a URL from the user, `normalizeURL` returning `''` for malformed input).

Config side: none needed — `ConfigSchema::DEFAULTS` already supplies every path key.

Env export in `Configuration::export()` already guarantees all values exist by the time scripts run.

### E. Push logic into JS

#### E.1 `src/js/info.js` (new)

Reads from env:
- `INVRT_CONFIG_FILE`, `INVRT_PLAN_FILE`, `INVRT_CAPTURE_DIR`, `INVRT_ENVIRONMENT`, `INVRT_PROFILE`, `INVRT_DEVICE`, `INVRT_ID`.

Reads YAML: project file (to list env/profile/device keys and project name) and plan file (page count).

Walks `INVRT_CAPTURE_DIR/reference/<device>` and `INVRT_CAPTURE_DIR/<env>/<device>` for `*.png` counts.

Writes a JSON object to stdout:
```json
{
  "name": "...", "id": "...", "config_file": "...",
  "environment": "...", "profile": "...", "device": "...",
  "environments": [...], "profiles": [...], "devices": [...],
  "planned_pages": N, "reference_screenshots": N, "test_screenshots": N
}
```

Drops the `crawl_log_tail` field entirely (per todo).

`Runner::info()` becomes:
```php
public function info(): int { return $this->runNode('info.js', null, null, captureStdoutAs: 'info'); }
```
…or simpler: new `infoJson(): string` that runs the node script and returns its stdout. `InfoCommand` decodes JSON and renders.

Alternative chosen: `Runner::info(): array` runs `info.js`, captures stdout, `json_decode`s, returns. Keeps the command unchanged except for the dropped `crawl_log_tail` section.

`InfoCommand` change: remove the `Crawl log (last 5 lines)` block and change label from `Crawled pages` to `Planned pages`.

#### E.2 `src/js/configure-playwright.js` (new)

Writes the playwright `defineConfig` TypeScript to `INVRT_PLAYWRIGHT_CONFIG_FILE`. Content is the same heredoc currently in `Runner::configurePlaywright()`.

`Runner::configurePlaywright()` becomes a one-liner: `return $this->runNode('configure-playwright.js');`.

No input, no output capture — the JS script writes directly to the file path it reads from env.

### F. Tests

- Existing PHPUnit unit tests that assert helper methods on `Runner` (e.g. `generateProjectId`, `normalizeURL`) — update to call the new `Service\ProjectId` / `Service\UrlNormalizer` classes, or drop if they duplicate E2E coverage.
- Bats tests should keep passing unchanged; the user-visible outputs of `info`, `reference`, `test`, `configure-backstop`, `configure-playwright` are preserved (except `info` drops the crawl-log tail).
- Add no new tests — existing E2E/Bats coverage already exercises every touched command.

### G. Docs

- `docs/spec/APP_SUMMARY.md`: update `info` output (drop crawl-log tail, rename "crawled pages" → "planned pages"); update `configure-backstop` input to `INVRT_PLAN_FILE`; update `reference`/`test` prerequisite description (plan file instead of crawl file).
- `TODO.md`: move these five items to `docs/planning/TODO-DONE.md` under a new dated section as they are completed.

## Order of Work

1. Extract `ProjectId` and `UrlNormalizer` services (mechanical move + update callers/tests).
2. Extract `PlaywrightRunner` service; drop `$env` arg.
3. Add `src/js/configure-playwright.js`; shrink `Runner::configurePlaywright()`.
4. Add `src/js/info.js`; rewrite `Runner::info()`; update `InfoCommand`.
5. Update `src/js/backstop-config.js` to read plan YAML; switch `Runner::configureBackstop()` input.
6. Replace crawl-file prereq checks in `reference()`/`test()` with plan-file checks; remove `validateCrawledUrls()`, `referencesAreMissing()`.
7. Strip defensive defaults throughout Runner.
8. Run `task test`. Fix fallout. Update `APP_SUMMARY.md`. Move todo entries to `TODO-DONE.md`.

## Out of Scope / Explicit Non-Goals

- No new CLI commands, flags, config keys.
- No changes to the config file format, `ConfigSchema`, or the merge order.
- No changes to login/cookie services.
- No filesystem-abstraction wrapper (see §A rationale).
- No PHPUnit/PHPStan/rector removal (that's a separate Tech Debt cluster under "Tests").
