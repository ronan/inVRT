# Plan: generate-playwright command

## Goal

Add a `generate-playwright` command (hidden) that reads crawled paths from `INVRT_CRAWL_FILE` and writes a Playwright TypeScript spec to `INVRT_SCRIPTS_DIR/playwright.spec.ts`.

The spec visits each crawled page, waits for the page to settle, and captures a screenshot to `INVRT_CAPTURE_DIR/INVRT_ENVIRONMENT/INVRT_DEVICE/{pageId}.png`.

## Changes

### 1. `docs/spec/Application.yaml`

Add `playwright_spec_file` to the Files section and a `generate-playwright` command entry:

```yaml
Files:
  playwright_spec_file:
    default: INVRT_SCRIPTS_DIR/playwright.spec.ts
    description: Generated Playwright spec file for screenshot capture.
```

Run `task build:templates` to regenerate `ConfigSchema.php`.

### 2. `src/js/generate-playwright.js`

New script. Reads crawled paths from stdin. Outputs a TypeScript Playwright spec to stdout.

Env vars used:
- `INVRT_URL` — base URL
- `INVRT_CAPTURE_DIR` — base dir for screenshots
- `INVRT_ENVIRONMENT` — environment name (subdirectory under capture dir)
- `INVRT_DEVICE` — device name (subdirectory under environment)
- `INVRT_PROFILE` — profile name (for cookie file path)
- `INVRT_COOKIES_FILE` — base path for cookie file (append `.json`)
- `INVRT_MAX_PAGES` — cap the number of pages (same as backstop-config.js)
- `INVRT_ID` — project ID for encodeId seed

The generated spec:
- Imports from `@playwright/test`
- Loads cookies from `INVRT_COOKIES_FILE.json` if it exists (via `storageState`)
- For each crawled path, generates one `test()` block that:
  - Navigates to the full URL
  - Waits for `networkidle`
  - Takes a screenshot saved to `{INVRT_CAPTURE_DIR}/{INVRT_ENVIRONMENT}/{INVRT_DEVICE}/{pageId}.png`
- Includes `encodeId` helper (same algorithm as backstop-config.js)

### 3. `src/core/Runner.php`

Add `generatePlaywright()` public method:

```php
public function generatePlaywright(): int
{
    $inputFile  = $this->config->get('INVRT_CRAWL_FILE', '') ?: null;
    $outputFile = $this->config->get('INVRT_PLAYWRIGHT_SPEC_FILE', '') ?: null;
    return $this->runNode('generate-playwright.js', $inputFile, $outputFile);
}
```

### 4. `src/cli/Commands/GeneratePlaywrightCommand.php`

New hidden command `generate-playwright`. Follows the same pattern as `ConfigureBackstopCommand`:

```php
#[AsCommand(name: 'generate-playwright', hidden: true)]
class GeneratePlaywrightCommand extends BaseCommand
{
    protected bool $requiresLogin = false;

    public function __invoke(SymfonyStyle $io, #[MapInput] InvrtInput $opts): int
    {
        if (($result = $this->boot($opts, $io)) !== Command::SUCCESS) {
            return $result;
        }
        return $this->runner->generatePlaywright() === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
```

### 5. `src/cli/invrt.php`

Register `GeneratePlaywrightCommand`.

### 6. Tests

Add a BATS test in `workflow.bats`:
- After crawl, run `generate-playwright`
- Assert `scripts/playwright.spec.ts` exists
- Assert it contains the crawled URLs' page IDs

## Order of Operations

1. Edit `docs/spec/Application.yaml` (add `playwright_spec_file`)
2. Run `task build:templates`
3. Create `src/js/generate-playwright.js`
4. Add `generatePlaywright()` to `Runner.php`
5. Create `src/cli/Commands/GeneratePlaywrightCommand.php`
6. Register in `src/cli/invrt.php`
7. Add BATS test
8. Run `task test`
