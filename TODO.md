# TODO

Tracks planned features, bugs, test gaps, and tech debt.
For AI agents and human developers.

## Checkbox status key

- `- [ ]` open · `- [x]` done. `- [-]` partially completed. `- [.]` in progress. `- [#]` not ready

## Bugs

- [x] The path of playwright-onbefore.js and playwright-onload.js are incorrect

        We're looking in the user-scripts directory but it's in the app source code directory.

- [x] INVRT_MAX_PAGES is not being applied during reference and test

- [x] Backstop fails when urls (and therefore file paths) are too long

## Tech Debt

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

- [ ] Move js source into `core/js` and refactor and hardcode the path in the php code. 

## Documentation

- [x] Create `docs/APP_SUMMARY.md` — a brief, agent-optimized application summary

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
- [ ] Clean up the repository
    - Reduce the number of files and directories at the top level of the repository
    - Make sure special files (AGENTS.md, README.md) which are supposed to live at the repo root remain there.
    - Any file not required to be at the root should be moved if possible
    - If moving functional files such as tool configuration, make whatever changes are needed so  that functionality doesn't break.
    - Remove the chats directory
    - Ensure all source code is in `src/` separated by lanaguage and focus to make testing/linting/building easier.
        - `src/core` - Core functionality (php)
        - `src/js`   - Playwright and other scripts (js)
        - `src/cli`  - The cli runner code (php, symfony console)
    - Update testing and linting to point to the correct folders
    - Clean up `docs/`
        - End-user documentation should go in `docs/user/en`
        - Developer documentation should go in `docs/developer/en`
        - Planning and specification documents should go into `docs/planning/`
            - AGENTS.md and README.md should remain at the top of the repository
        - Github pages assets (such as index.html) should go in `docs/website`
        - Move agent plan documents from `plans/` to `docs/planning/agent-plans`
            - Update the AGENTS.md and SKILLS.md file to reflect that change and ensure that agents use that directory to save plans.
    - Clean up `tooling/` directory
        - Remove unused config files
        - Move config files into `tooling/config` and update the Taskfile.yml where needed
    - Move completed todo items into docs/planning/TODO-DONE.md
    - Make other suggestions that will make the repository cleaner and easier to navigate whithout breaking existing functionality or tools. Use industry best practices for FOSS tools.

## Tests

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


### CMS-Specific Testing

  - [ ] Test backdrop support
  - [ ] Test drupal auth support
  - [ ] Set up ddev to run during tests
    
        Challenges: Can we use docker-outside-of-docker to control ddev?
        DDev doesn't want to be root but it get's permission errors.

## Features

### General operations

 - [ ] Add an `invrt info` command that returns nicely formatted info about the current project

        The output should include: current config, environments, devices, profiles, number of crawled pages, number of captured screenshots and the last few lines of the crawl.log

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

### Advanced flow
- [ ] Implement `invrt check` to load the homepage and retrieve the site title
    - Have the check function run automatically after init and before crawl if it hasn't been run yet.
    - Add cms_detector binary to dockerfile to check the cms version/platform.
    - Create a check.yml file with info from the check including:
        - Site Title
        - URL (if the specified url leads to a permament redirect)
        - Supports https?
        - CMS/Platform (eg: drupal, backdrop, wordpress) via cms_detector
        - Last check date
        - Any other information that may be useful for crawling or capturing screenshots
- [ ] Save reference output to 'INVRT_CAPTURE_DIR/reference_results.txt', save test results to 'INVRT_CAPTURE_DIR/test_results.txt'
- [ ] Use generated config files to determine which steps have been run at least once
    - Init has run if a 'check.yaml' file exists
    - Crawl has run if a 'crawled_urls.txt' file exists
    - Reference has run if a 'reference_results.txt' file exists
    - Test has run if a 'test_results.txt' file exists
- [#] Create a function that converts crawled_urls.txt to the format in SITE_TREE_FILE_SPEC.md
  - [ ] Name the file 'plan.yaml' and put it at the top of the .invrt directory
  - [ ] Update the document when new paths are found when crawling with different profiles
  - [ ] Turn 'plan.yaml' into 'test.json' with backstop test config in it.

### User scripting

- [ ] Optionally read the onload/onready playwright event script from `INVRT_SCRIPTS_DIR`
- [ ] Allow specification of per-path scripts in plan.yaml
- [ ] Allow the user to specify setup/teardown scripts per project

### Reporting

- [ ] Create a 1 page html report for all existing test results
- [ ] Create an "Interactive" report
    - [ ] Allow tests to be re-run
    - [ ] Allow differences to be approved
    - [ ] Allow comparison of different environments
    - [ ] Allow comparison of different profiles

### Future Features
- [ ] Advanced playwright integration
- [ ] Better debug output during crawl
- [ ] Rewrite the crawler
    - Make exclude_paths work and provide defaults for drupal/backdrop
    - add a max_width to go with max_depth
