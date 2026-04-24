# TODO Done

Completed tasks moved from TODO.md.

## Config

- [x] Make an invalid config a warning instead of a fatal error.
  - If the config file loads as valid yaml but doesn't pass the custom validation step (ie: if it has unexpected values), show a warning explaining the issue in a friendly user readable way but continue as long as the url value is valid and readable.

## Tech Debt

- [x] Refactor Runner to only contain public command methods
  - Extracted `Service\PlaywrightRunner`, `Service\ProjectId`, `Service\UrlNormalizer`, `Service\Filesystem`
  - Runner now holds only public command methods + one private `runNode()` dispatcher
  - Dropped private helpers `validateCrawledUrls`, `referencesAreMissing`, `countScreenshots`, `readLogTail`, `writeResultsFile`

- [x] Use INVRT_PLAN_FILE instead of INVRT_CRAWL_FILE where appropriate
  - `configure-backstop` now reads plan.yaml instead of crawled-paths.text
  - `reference`/`test` prerequisite checks look at `plan.yaml` pages
  - Crawl file remains the output artifact of `crawl.js` only

- [x] Remove `array $env` from `runPlaywright` and use `$this->config` directly
  - Now lives in `Service\PlaywrightRunner` which holds `Configuration` directly

- [x] Remove config validation and defaults from Runner
  - Runner no longer supplies fallback values for keys that have defaults in `ConfigSchema`
  - Guards for unset `INVRT_PLAYWRIGHT_CONFIG_FILE`, `INVRT_PLAN_FILE`, etc. removed — trust the config handler

- [x] Remove more business logic from Runner.php and move to the ts scripts
  - Added `src/js/info.js` (replaces the PHP `info()` body; drops the crawl-log tail)
  - Added `src/js/configure-playwright.js` (replaces the heredoc in Runner)

## Tech Debt (earlier)

- [x] Move more logic to js/node
  - Created `src/js/check.js` and `src/js/crawl.js` for site check and crawl operations
  - Refactored `backstop-config.js` to export `generateBackstopConfig()` so crawl.js can call it directly
  - Slimmed `Runner.php`: `check()` and `crawl()` are now thin `runNode()` wrappers; removed 6 private helpers
  - Updated `NodeOutputParser` to map pino level 30 → PSR-3 `notice` (shows at default verbosity)

- [x] Standardize output from js/node
  - Use pino to write output from node
    - https://getpino.io
  - Allow the runner to read the responses along with their level and call the appropriate PSR-3 log function on $logger to send output to the user or to a text log depending on verbosity.
    - pino: trace, debug, info, warn, error, and fatal
    - psr3: Debug, Info, Notice, Warning, Error, Critical, Alert, and Emergency
      - trace = Debug
      - fatal = Emergency

## Reporting

  - [x] Add a project id to distinguish final reports.
    - [x] The project id should be saved to the config.yml file as 'id' in settings

## Move to Playwright (phase 2)

- [x] Put the contents of `tooling/config/playwright.config.ts` to the CRAWL_DIR before running `generate-playwright`
    - Created hidden `configure-playwright` command that writes the hardcoded config content to `INVRT_PLAYWRIGHT_CONFIG_FILE`
    - `generate-playwright` now calls `configure-playwright` first
    - Config file path controlled by `INVRT_PLAYWRIGHT_CONFIG_FILE`
- [x] Run references and test capture by running the playwright test script
    - `reference` now runs `generatePlaywright()` + `runPlaywright('reference')` (with `--update-snapshots`)
    - `test` now runs `runPlaywright('test')`
    - `approve` now runs `runPlaywright('reference')` (re-captures with `--update-snapshots`)
    - `baseline` updated to use the Playwright pipeline end-to-end
    - Removed `runBackstop()`, `ensureBackstopConfig()`, and `prepareDirectory()` from Runner


    - save to INVRT_DIRECTORY/scripts/playwright.spec.ts
    - test steps
      - Visits each page in the crawl list
      - Waits for the content to load and settle
      - Take a screenshot and save it as data/bitmaps/{environment}/`{pageID}_{profile}_{device}`
    - [x] It should be based on the url and a unique seed

- [x] Improve page ids
    - [x] Use the existing `Runner::encodeId` function in `src/core/Runner.php`
    - [x] Use a 4 byte number derived from the project_id as a seed.
    - [x] Add the page id to the page's scenario in backstop.js as the 'label'

## Bugs

- [x] Init throws an error "[error] INVRT_URL must be set" even when a url is passed as an argument
  - `Runner::init()` now seeds in-memory `INVRT_URL`/`INVRT_ID` before calling `check()`
  - Prevents false `INVRT_URL` missing error before command-level re-boot

- [x] Exclud path file is not being read.

- [x] The path of playwright-onbefore.js and playwright-onload.js are incorrect

        We're looking in the user-scripts directory but it's in the app source code directory.

- [x] INVRT_MAX_PAGES is not being applied during reference and test

- [x] Backstop fails when urls (and therefore file paths) are too long

## Tech Debt

- [x] reduce unnecessary code from php to make test run steps more self contained

- [x] move file generation to js/ts

- [x] Clean up config and get schema generation working again.
  - [x] Remove unnecessary file path config
  - [x] Clean up the schema generator
  - [x] Teach the ai agents how to edit the schema instead of altering the generated code.

- [x] Remove the `settings` section and replace it with `project`

- [x] Clean up file structure
    - `check.yaml` and `exclude-paths.txt` moved to `.invrt/` root
    - Crawl artifacts at `data/PROFILE/` (environment removed from path)
    - `crawled_urls.txt` renamed to `crawled-paths.text`
    - Bitmaps reorganized: `data/PROFILE/bitmaps/reference/DEVICE` (approved baseline) and `data/PROFILE/bitmaps/ENV/DEVICE` (test runs)
    - `backstop.json` moved to `scripts/backstop.json`
    - `init` creates empty `scripts/onready.js` placeholder
    - Logs at `data/PROFILE/logs/` with simple names (`crawl.log`, `reference.log`, `test.log`)

## Features

### Advanced flow

- [x] Generate page ids during crawl and add them to plan.yaml
  - Crawl now writes stable `id` values to discovered page entries in `plan.yaml`

- [x] Improve the crawler to build a tree-like structure for nested pages.
  - Nested paths are grouped under shared branch prefixes in `pages`
  - Query pages are stored as `?query` children under their path branch
  - Navigable branch pages are represented with landing child keys (`''` or `/`) where applicable

- [x] Rebuild `invrt generate-playwright` to use plan.yaml to create tests
  - `Runner::generatePlaywright()` now pipes `INVRT_PLAN_FILE` to `generate-playwright.js`
  - `generate-playwright.js` parses plan YAML and generates tests from `pages` path keys

- [x] Rewrite the crawler
  - Uses Playwright to crawl from plan.yaml seed paths
  - Scrapes links and adds in-scope HTML pages back into plan.yaml
  - Repeats through discovered paths up to depth/page limits
  - Updates each discovered page with `profiles` array entries for access by profile

- [x] Create a plan.yaml file (at `.invrt/plan.yaml`) during init.
  - Uses the format in [Plan Yaml Spec](docs/planning/proposals/Plan.yaml.specification.md)
  - `plan.yaml` is auto-generated and updated but remains user-editable
  - User-defined keys are preserved when updates run
  - `invrt init` adds base URL to `project`
  - `invrt check` adds discovered title and ensures homepage entry in `pages` (`/`)

- [x] Implement `invrt check` to load the homepage and retrieve the site title
  - Have the check function run automatically after init and before crawl if it hasn't been run yet.
  - Add cms_detector binary to dockerfile to check the cms version/platform.
  - Create a check.yml file with info from the check including:
    - Site Title
    - URL (if the specified url leads to a permament redirect)
    - Supports https?
    - CMS/Platform (eg: drupal, backdrop, wordpress) via cms_detector
    - Last check date
    - Any other information that may be useful for crawling or capturing screenshots
- [x] Save reference output to 'INVRT_CAPTURE_DIR/reference_results.txt', save test results to 'INVRT_CAPTURE_DIR/test_results.txt'
- [x] Use generated config files to determine which steps have been run at least once
  - Init has run if a 'check.yaml' file exists
  - Crawl has run if a 'crawled_urls.txt' file exists
  - Reference has run if a 'reference_results.txt' file exists
  - Test has run if a 'test_results.txt' file exists
- [x] Improve `approve` to make the last results of the last test the new baseline
  - If no tests have been run, run `crawl`, `reference` then `test` and then approve the capture

- [x] Implement `baseline`
    - Runs full pipeline: check → crawl → configure-backstop → reference → test → approve
    - `baseline` runs automatically after `init` unless `--skip-baseline` is set.

- [x] Move 'configure-backstop' into a new command.
  - [x] Make reference auto-trigger this step when needed.

- [x] Create a stdin/stdout based pipeline.
  - Node scripts (check, crawl, backstop-config, backstop) write output to stdout; log to stderr.
  - PHP Runner owns all file reading (stdin) and writing (stdout capture → output file).
  - `runNode()` accepts optional `$inputFile`/`$outputFile` params.
  - This more accurately reflects the fact that these are the base defaults for the given project.
  - Move `name` into project.

- [x] Update AGENTS.md new command guidance for Symfony 8 command patterns

- [x] **Refactor `src/invrt-reference.sh`**

- [x] **Refactor `src/invrt-test.sh`**

- [x] Remove use of 'passthru' in php for testibility

- [x] Refactor the php command codebase

        There is a lot of repeated code.

- [x] Refactor config handling to use symfony/congig

  - [x] Rewrite config handling documentation `docs/configuration.yml`
  - [x] Check the documentation against code and tests to find any inconsistencies or ambiguities
  - [x] Rewrite the config handling tests for clarity and brevity
  - [x] Rewrite the app config handling

        Use the [symfony config component](https://symfony.com/doc/current/components/config.html) to rewrite and simplify config handling

- [x] Replace the custom joinPath function with symfony/filesystem

- [x] Use symfony dependency injection for configuration passing

        See: https://symfony.com/doc/current/service_container.html
        Use DI/Service container and autowiring to pass the config object to commands
        Remove the $this->withEnv pattern and simplofy the controllers as much as possible

- [x] Do a manual refactor of the config/options system to get rid of the last of the code smells

- [x] Create a core php library independent of any framework and with minimal dependencies
    - Refactor all business logic out of the /src directory turn it into reusable classes in the /core directory
    - Use the namespace InVRT/Core
    - Provide a Configuration class which takes a filepath (for the config file) and an environment variable array and has the following public methods:
        - ->get($key, $default);
        - ->set($key, $value);
        - ->write();
    - Provide a Runner class which has the following public methods (each representing a command)
        - ->init();
        - ->config();
        - ->crawl();
        - ->reference();
        - ->test();
    - Move the rest of the Console app into a directory called /cli and rewrite it to use the new core library.
    - Make sure all of the existing tests still pass.
- [x] Move js source into `src/js` and hardcode the path in the php code.

## Documentation

- [x] Create `docs/APP_SUMMARY.md` — a brief, agent-optimized application summary

## Features

- [x] Generate backstop.json at the end of the crawl run
  - [x] Separate out the config generation code from the test/reference running code
  - [x] Keep the processing in js

- [x] Add an `invrt info` command that returns nicely formatted info about the current project

        The output should include: current config, environments, devices, profiles, number of crawled pages, number of captured screenshots and the last few lines of the crawl.log

## Developer Experience

- [x] Add better debugging output to the cli when run with `-vvv` (https://symfony.com/doc/current/console/verbosity.html)
- [x] Add ddev-invrt addon into main repo
- [x] Add automatic versioning
    - Use semantic versioning (eg. 1.0.1 etc)
    - Create a version:bump task to
        - Bump to the next patch version
        - Update the documentation
        - Build, tag and publish a new docker build
        - Build and publish the ddev-invrt addon to github

## Tests

- [x] Add all e2e tests to bats.

- [x] **E2E: ReferenceCommandTest**

- [x] **E2E: TestCommandTest**

- [x] Improve e2e tests/E2E/CrawlCommandTest.php

      Use the same test fixture website that are used in tests/E2E/ReferenceCommandTest.php and tests/E2E/TestCommandTest.php. Expand the test website to include 5 web pages to crawl.

- [x] Slim down test suite

      Reduce the number of PHP tests in tests/. Leave only the tests of the core functionality. Don't test error handling. Don't test yaml parsing ot cookie handling. Simplify the config handling. Look for other ways to simplify and improve tests.

- [x] Clean up tests
    - [x] Rename the tests/E2E directory to tests/e2e and update references
    - [x] Combine e2e tests to reduce the number of times the command needs to be run

        - use tests/e2e/CrawlCommandTest.php as an example

    - [x] Write BATS tests (https://bats-core.readthedocs.io/en/stable/) to cover the cli commands
        - Refer to /docs/APP_SUMMARY.md for app behavior to test
        - Add task commnands to install bats and to run the tests

    - [x] Replace the e2e phpunit tests with BATS tests

## Features

### User Scripting

- [x] Allow specification of per-path test scripts in plan.yaml
  - `generate-playwright` now reads `setup`, `onready`, and `teardown` hooks from page metadata
- [x] Scripts can be written in javascript or typescript
  - `.js` and `.ts` hook files are both supported
- [x] Scripts on a parent path apply to all children
  - Hook metadata is inherited down the `pages` tree unless a child overrides a hook
- [x] User scripts get added to the .spec.ts file by `generate-playwright`
  - Generated Playwright specs embed resolved user hook code for each page test
- [x] Scripts will be run by `playwright test` during one of the following events:
  - `setup`, `teardown`, `onready`
- [x] The script or script path should be added to plan.yml under a key that represents its event
  - `before`, `ready`, and `after` are also accepted as aliases for compatibility
- [x] Scripts can be the name of a script in `INVRT_SCRIPTS_DIR`
  - Bare filenames resolve relative to `.invrt/scripts/`
- [x] Scripts can be literal codeblock in the plan.yaml
  - Any string that does not end in `.ts` or `.js` is treated as raw inline code
- [x] Generate an empty onready.ts script during `init` that applies at the root path. Add a single line comment to describe what the file is used for.
  - `init` now scaffolds `.invrt/scripts/onready.ts` with a descriptive comment
- [x] Generate the playwright test file from plan.yaml before running reference or test
  - `reference` and `test` now ensure the Playwright spec is regenerated from `plan.yaml` before execution

### Baseline/Test/Report flow

- [x] Auto trigger `invrt reference` when `invrt test` is run for first time
- [x] Return error when `invrt crawl` finds no usable urls.
  - Show the last 5 lines of the crawl.log
  - Create an empty crawled_urls.txt file to indicate to the
      reference command that crawl has run and failed so that it doesnt' trigger another crawl.
- [x] Auto trigger `invrt crawl` when `invrt reference` is run for the first time
- [x] Return error when `invrt reference` finds no crawled urls

      If invrt_crawl has already run but there are no urls in the crawled_urls.txt
      file invrt reference should return with an error.

- [x] Add a url argument to the `invrt init` command
    - Remove the code that adds the default config file.
    - Save the passed in url to a new fresh config.yaml.
    - Respect the passed in config parameters to seve it to the right section of config.yaml
- [x] Auto trigger `invrt init` when `invrt crawl` is run for the first time
- [x] Implement `invrt approve` which:
    - runs `backstop approve`
- [x] Make a `invrt baseline` command which:
    - runs `invrt reference` if needed
    - runs `invrt test` if needed
    - runs `invrt approve`
- [x] Implement an interactive init mode
  - Prompt the user for a url
