# TODO Done

Completed tasks moved from TODO.md.

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
- [x] Move js source into `src/js` and hardcode the path in the php code.

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

## Features

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
