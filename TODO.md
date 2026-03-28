# TODO

Tracks planned features, bugs, test gaps, and tech debt.
For AI agents and human developers.

- `- [ ]` open · `- [x]` done
- Reference the relevant file or doc when adding an item.


## Bugs

_(none yet)_

## Tech Debt

- [x] **Refactor `src/invrt-reference.sh`**

- [x] **Refactor `src/invrt-test.sh`**

- [x] Remove use of 'passthru' in php for testibility

- [ ] Refactor the php command codebase

        There is a lot of repeated code.

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



- [ ] Test drupal auth support

- [ ] Test backdrop support

## Features

- [ ] More config functionality
        - [ ] specify a specific key
                invrt config --key=title,url --environment-dev
        - [ ] update or add a key to config.yml
                invrt config set --key=title,url --value="Hello, World",http://example.com

- [ ] **Add WordPress support**
