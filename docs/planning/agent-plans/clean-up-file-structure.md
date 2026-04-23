# Plan: Clean Up File Structure

## Goal

Reorganize the `.invrt/` directory layout to:
- Remove `INVRT_ENVIRONMENT` from crawl paths (crawl is per-profile, not per-environment)
- Move `check.yaml` and `exclude-paths.txt` to the `.invrt/` root
- Rename `crawled_urls.txt` → `crawled-paths.text`
- Reorganize bitmaps under `data/PROFILE/bitmaps/` with `reference/DEVICE` for the approved baseline and `ENV/DEVICE` for test runs
- Move `backstop.json` into `scripts/`
- Keep logs under the profile dir at `data/PROFILE/logs/` with simple names
- Have `init` create an empty `scripts/onready.js` placeholder

## Desired Structure

```
.invrt/
  config.yaml
  exclude-paths.txt
  check.yaml                        ← was data/ENV/check.yaml
  data/
    anonymous/                      ← was data/ENV/PROFILE/ (INVRT_CRAWL_DIR)
        crawled-paths.text          ← was INVRT_CRAWL_DIR/crawled_urls.txt
        cookies(.json/.text)
        clone/
        bitmaps/
            reference/              ← approved baseline (bitmaps_reference)
                desktop/            ← INVRT_CAPTURE_DIR
            local/                  ← test run per environment (bitmaps_test)
                desktop/
            production/
        logs/
            crawl.log               ← was data/ENV/PROFILE/logs/crawl.log
            reference.log           ← was data/ENV/PROFILE/DEVICE/reference_results.txt
            test.log                ← was data/ENV/PROFILE/DEVICE/test_results.txt
  scripts/
    backstop.json                   ← was data/ENV/PROFILE/
    onready.js                      ← an empty file created by 'init' for the user to edit.
```

## Changes

### 1. `docs/spec/Application.yaml` (source of truth for paths)

| Key | Old default | New default |
|-----|-------------|-------------|
| `crawl_dir` | `INVRT_DIRECTORY/data/INVRT_ENVIRONMENT/INVRT_PROFILE` | `INVRT_DIRECTORY/data/INVRT_PROFILE` |
| `capture_dir` | `INVRT_DIRECTORY/data/INVRT_ENVIRONMENT/INVRT_PROFILE/INVRT_DEVICE` | `INVRT_CRAWL_DIR/bitmaps` |
| `crawl_file` | `INVRT_CRAWL_DIR/crawled_urls.txt` | `INVRT_CRAWL_DIR/crawled-paths.text` |
| `crawl_log` | `INVRT_CRAWL_DIR/logs/crawl.log` | `INVRT_CRAWL_DIR/logs/crawl.log` (unchanged) |
| `check_file` | `INVRT_DATA_DIR/INVRT_ENVIRONMENT/check.yaml` | `INVRT_DIRECTORY/check.yaml` |
| `backstop_config_file` | `INVRT_CAPTURE_DIR/backstop.json` | `INVRT_SCRIPTS_DIR/backstop.json` |
| `reference_file` | `INVRT_CAPTURE_DIR/reference_results.txt` | `INVRT_CRAWL_DIR/logs/reference.log` |
| `test_file` | `INVRT_CAPTURE_DIR/test_results.txt` | `INVRT_CRAWL_DIR/logs/test.log` |
| `exclude_file` | `INVRT_CRAWL_DIR/exclude_paths.txt` | `INVRT_DIRECTORY/exclude-paths.txt` |

After editing Application.yaml, run `task build:templates` to regenerate `ConfigSchema.php`.

### 2. `src/js/backstop-config.js`

Update the BackstopJS paths config — bitmaps are now separated by environment (test) vs. `reference` (approved baseline), with device as the leaf:
- `bitmaps_reference`: `INVRT_CAPTURE_DIR + "/bitmaps/reference"` → `INVRT_CAPTURE_DIR + "/reference/" + INVRT_DEVICE`
- `bitmaps_test`: `INVRT_CAPTURE_DIR + "/bitmaps/test"` → `INVRT_CAPTURE_DIR + "/" + INVRT_ENVIRONMENT + "/" + INVRT_DEVICE`
- Add `INVRT_ENVIRONMENT` and `INVRT_DEVICE` to the destructured env vars (if not already present)

### 3. `src/core/Runner.php`

- `reference()`: change `prepareDirectory($captureDir)` to clear only the reference+device dir:
  `prepareDirectory($captureDir . '/reference/' . $device)` — add `$device = $env['INVRT_DEVICE']`
- `baseline()`: replace single `prepareDirectory($captureDir)` with two calls:
  - `prepareDirectory($captureDir . '/reference/' . $device)`
  - `prepareDirectory($captureDir . '/' . $environment . '/' . $device)`
  - Add `$device = $env['INVRT_DEVICE'] ?? 'desktop'`
- `info()`: update `countScreenshots` paths:
  - `$captureDir . '/bitmaps/reference'` → `$captureDir . '/reference/' . $device`
  - `$captureDir . '/bitmaps/test'` → `$captureDir . '/' . $environment . '/' . $device`
  - Add `$device = $env['INVRT_DEVICE'] ?? 'desktop'`
- `init()`: after creating `scripts/` directory, touch an empty `scripts/onready.js` placeholder

### 4. `tests/bats/test_helper.bash`

- Update `seed_crawled_urls` helper: change path from `data/$environment/$profile` → `data/$profile` and filename from `crawled_urls.txt` → `crawled-paths.text`. Drop the `$environment` first-arg since environment is no longer part of the crawl dir.

### 5. `tests/bats/workflow.bats`

Update all path assertions and `seed_crawled_urls` call sites:

| Old path | New path |
|----------|----------|
| `data/local/check.yaml` | `check.yaml` |
| `data/local/anonymous/crawled_urls.txt` | `data/anonymous/crawled-paths.text` |
| `data/local/anonymous/logs/crawl.log` | `data/anonymous/logs/crawl.log` |
| `data/local/anonymous/desktop/backstop.json` | `scripts/backstop.json` |
| `data/local/anonymous/desktop/bitmaps/reference` | `data/anonymous/bitmaps/reference/desktop` |
| `data/local/anonymous/desktop/bitmaps/test` | `data/anonymous/bitmaps/local/desktop` |
| `data/local/anonymous/desktop/reference_results.txt` | `data/anonymous/logs/reference.log` |
| `data/local/anonymous/desktop/test_results.txt` | `data/anonymous/logs/test.log` |

`seed_crawled_urls` call sites: drop the leading environment arg (e.g. `seed_crawled_urls local anonymous / /about.html` → `seed_crawled_urls anonymous / /about.html`).

## Order of Operations

1. Edit `docs/spec/Application.yaml`
2. Run `task build:templates` to regenerate `ConfigSchema.php`
3. Edit `src/js/backstop-config.js`
4. Edit `src/core/Runner.php`
5. Edit `tests/bats/test_helper.bash`
6. Edit `tests/bats/workflow.bats`
7. Run `task test`
