# Generate backstop.json at the end of the crawl run

## Goal
Separate backstop config generation from BackstopJS operation execution. Generate `backstop-config.json` after crawl completes, before reference/test operations.

## Current Behavior
1. `Runner::crawl()` discovers URLs and saves to `INVRT_CRAWL_FILE`
2. `Runner::reference()` / `Runner::test()` call `runBackstop()` with mode
3. `backstop.js` reads crawled URLs, builds config, saves to `backstop-config.json`, then runs BackstopJS operation

## Desired Behavior
1. `Runner::crawl()` discovers URLs, saves to `INVRT_CRAWL_FILE`, then generates backstop config
2. `Runner::reference()` / `Runner::test()` call `runBackstop()` with mode — config already exists
3. `backstop.js` loads pre-generated config instead of rebuilding it each time

## Implementation Plan

### 1. Extract config generation from `backstop.js`
- Create new script `src/js/backstop-config.js` that:
  - Takes `--config` or similar argument or reads from env
  - Builds backstop config from crawled URLs
  - Writes `backstop-config.json` to `INVRT_CAPTURE_DIR`
  - Exits 0 on success

### 2. Update `backstop.js`
- Load pre-generated config from `INVRT_CAPTURE_DIR/backstop-config.json`
- Fallback to generating it inline for backwards compatibility (optional)
- Remove config generation logic

### 3. Update `Runner::crawl()`
- After writing crawled URLs to `INVRT_CRAWL_FILE`
- Call `runBackstopConfig()` to generate the config file
- Log config generation result

### 4. Add `Runner::generateBackstopConfig()` method
- Executes `backstop-config.js` via Node
- Similar pattern to `runBackstop()`

## Files to Change
- `src/js/backstop-config.js` — new file
- `src/js/backstop.js` — load pre-generated config
- `src/core/Runner.php` — add config generation after crawl

## Testing
- Existing E2E tests should pass without changes (backstop.js fallback)
- Verify `backstop-config.json` exists after `invrt crawl`
- Verify config is used by reference/test commands
