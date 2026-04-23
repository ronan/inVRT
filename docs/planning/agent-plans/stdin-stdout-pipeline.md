# Plan: stdin/stdout-based pipeline

## Goal

Move file I/O out of the Node scripts. PHP Runner owns all file reading/writing. Node scripts communicate via stdin (input) → stdout (data output), with log messages on stderr.

## Pipeline contract per command

| Command            | Runner reads → stdin          | stdout → Runner writes         |
|--------------------|-------------------------------|-------------------------------|
| check              | (none)                        | INVRT_CHECK_FILE               |
| crawl              | (none)                        | INVRT_CRAWL_FILE               |
| configure-backstop | INVRT_CRAWL_FILE              | INVRT_BACKSTOP_CONFIG_FILE     |
| reference / test   | INVRT_BACKSTOP_CONFIG_FILE    | (no data output; log → results file) |

Log messages (pino JSON) move from stdout → **stderr**. Runner routes stderr to `NodeOutputParser`.

---

## Changes

### 1. `src/js/logger.js`
Route pino to `process.stderr`:
```js
module.exports = pino({ level: 'trace', base: null, timestamp: false }, process.stderr);
```

### 2. `src/js/check.js`
- Write YAML to `process.stdout.write(...)` instead of `fs.writeFileSync(INVRT_CHECK_FILE, ...)`
- Remove `INVRT_CHECK_FILE` usage, dir creation

### 3. `src/js/crawl.js`
- Remove auto-run of `check.js` (Runner handles dependencies)
- Remove `generateBackstopConfig()` call at end (separate step)
- Remove `fs.writeFileSync(INVRT_CRAWL_FILE, ...)` and `fs.unlinkSync` of crawl file
- Write paths to `process.stdout.write(paths.join('\n'))`
- Remove `INVRT_CRAWL_FILE` and `INVRT_CHECK_FILE` from destructured env vars

### 4. `src/js/backstop-config.js`
- Read crawl paths from stdin (`process.stdin`) instead of `INVRT_CRAWL_FILE`
- Write JSON to `process.stdout.write(...)` instead of `fs.writeFileSync(outputFile, ...)`
- Remove `INVRT_CRAWL_FILE` and `INVRT_BACKSTOP_CONFIG_FILE` from destructured env vars
- Remove module export (`generateBackstopConfig`); script is standalone only

### 5. `src/js/backstop.js`
- Read config JSON from stdin instead of `fs.readFileSync(configFile, ...)`
- Remove `INVRT_BACKSTOP_CONFIG_FILE` usage and file-existence check
- Keep mode arg (`process.argv[2]`)

### 6. `src/core/Runner.php` — `runNode()`
Change signature from `runNode(string $script, string ...$args)` to:
```php
private function runNode(string $script, ?string $inputFile = null, ?string $outputFile = null): int
```
- If `$inputFile` is set and readable, `$process->setInput(file_get_contents($inputFile))`
- In the process callback: route `Process::ERR` → `$parser->write()`, `Process::OUT` → `$stdout .= $buffer`
- After process exits: if `$outputFile` set and `$stdout` non-empty, create dir + write file

### 7. `src/core/Runner.php` — `runBackstop()`
- Read `INVRT_BACKSTOP_CONFIG_FILE` and set as process stdin
- Route only `Process::ERR` to `NodeOutputParser`; ignore stdout
- Keep `writeResultsFile($mode, $parser->getMessages())`

### 8. `src/core/Runner.php` — command methods
- `check()`: `runNode('check.js', null, $this->config->get('INVRT_CHECK_FILE', '') ?: null)`
- `crawl()`: `runNode('crawl.js', null, $this->config->get('INVRT_CRAWL_FILE', '') ?: null)`
- `configureBackstop()`: `runNode('backstop-config.js', $crawlFile, $backstopConfigFile)`

---

## No changes needed
- `NodeOutputParser` — already handles partial lines correctly; PHP now only feeds it stderr
- All Commands, Configuration, ConfigSchema, tests (behavior unchanged from user perspective)
