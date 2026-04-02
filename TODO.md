# TODO

Tracks planned features, bugs, test gaps, and tech debt.
For AI agents and human developers.

## Checkbox status key

- `- [ ]` open · `- [x]` done. `- [-]` partially completed. `- [.]` in progress.

## Bugs

_(none yet)_

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




## Developer Experience

- [ ] Add script called (tooling/scrupts/generate-schema.mjs) to generate `docs/config.schema.yaml` from `docs/config.example.yaml`
- [ ] Add ddev-invrt addon into main repo

## Tests

- [x] **E2E: ReferenceCommandTest**

- [x] **E2E: TestCommandTest**

- [x] Improve e2e tests/E2E/CrawlCommandTest.php

        Use the same test fixture website that are used in tests/E2E/ReferenceCommandTest.php
        and tests/E2E/TestCommandTest.php. Expand the test website to include 5 web pages to crawl.

- [x] Slim down test suite

         Reduce the number of PHP tests in tests/. Leave only the tests of the core functionality. Don't test error handling. Don't test yaml parsing ot cookie handling. Simplify the config handling. Look for other ways to simplify and improve tests.

- [x] Clean up tests
        - [x] Rename the tests/E2E directory to tests/e2e and update references
        - [x] Combine e2e tests to reduce the number of times the command needs to be run
                use tests/e2e/CrawlCommandTest.php as an example

### CMS-Specific Testing
  - [ ] Set up ddev to run during tests
  - [ ] Test drupal auth support
  - [ ] Test backdrop support

## Features

### Baseline/Test/Report flow

  - [ ] Auto trigger `invrt test` when `invrt report` is run for the first time
  - [x] Auto trigger `invrt reference` when `invrt test` is run for first time
  - [ ] Auto trigger `invrt crawl` when `invrt reference` is run for the first time
  - [ ] Auto trigger `invrt init` when `invrt crawl` is run for the first time
  - [ ] Implement an interactive init mode


### Options and Configuration (`invrt contig`)

- [ ] Retrieve a specific config key or multiple keys

        invrt config --key=viewport_width --environment=dev --device=mobile
        invrt config --key=title,url --environment=dev

- [ ] update or add a key to config.yml

        invrt config set --key=title --value="Hello, World"
        invrt config set --key=profile.admin.name --value="Admin User"
        invrt config set --key=title,settings.url --value="Hello, World",http://example.com

### User scripting

- [ ] Optionally read the onload/onready playwright event script from `INVRT_SCRIPTS_DIR`
- [ ] Allow the user to specify setup/teardown scripts per project

### Reporting

- [ ] Create a 1 page html report for all existing test results
- [ ] Create an "Interactive" report
  - [ ] Allow tests to be re-run
  - [ ] Allow differences to be approved
  - [ ] Allow comparison of different environments
  - [ ] Allow comparison of different profiles