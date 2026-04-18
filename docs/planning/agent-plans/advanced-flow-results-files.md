# Advanced Flow: Results Files & Step Detection

## Problem

Two open todos in the **Advanced flow** section:

1. **Save reference/test output** — After `reference` and `test` runs, write BackstopJS output to `INVRT_CAPTURE_DIR/reference_results.txt` and `INVRT_CAPTURE_DIR/test_results.txt`.

2. **Use result files to detect completed steps** — Instead of checking for PNG bitmaps, use the presence of these result files to determine whether reference/test have been run. Aligns with the existing pattern (crawl → `crawled_urls.txt`, check → `check.yaml`).

## Approach

### 1. Add new config keys (`ConfigSchema.php`)

Add to `DEFAULTS` and the tree builder:

```php
'reference_results_file' => 'INVRT_CAPTURE_DIR/reference_results.txt',
'test_results_file'      => 'INVRT_CAPTURE_DIR/test_results.txt',
```

### 2. Capture and write output (`Runner.php`)

In `runBackstop()`, collect process output into a string (still `print`ing it live), then after the process finishes, write it to the appropriate results file:

- `reference` mode → `INVRT_REFERENCE_RESULTS_FILE`
- `test` mode → `INVRT_TEST_RESULTS_FILE`

The results file is written regardless of BackstopJS exit code (both success and failure count as "has run").

### 3. File-based step detection (`Runner.php`)

Replace the PNG-counting implementations of `referencesAreMissing()` and `testResultsAreMissing()` with simple file-existence checks:

- `referencesAreMissing()` → `!file_exists(INVRT_REFERENCE_RESULTS_FILE)`
- `testResultsAreMissing()` → `!file_exists(INVRT_TEST_RESULTS_FILE)`

Remove unused `$captureDir` parameters from these methods and their callers.

Because `reference()` calls `prepareDirectory($captureDir)` before running BackstopJS (which clears the capture dir), the old results file is automatically removed before a new one is written. Clean re-run semantics are preserved.

### 4. Docs (`docs/user/en/usage.md`)

Add `reference_results.txt` and `test_results.txt` to the Data Layout section.

### 5. Tests

- `ReferenceCommandTest`: assert `reference_results.txt` exists after a successful run.
- `TestCommandTest`: assert `test_results.txt` exists after a successful run.
- `TestCommandTest`: assert second `test` call does NOT re-trigger reference (results file present).
