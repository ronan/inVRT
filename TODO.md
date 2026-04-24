# TODO

Tracks planned features, bugs, test gaps, and tech debt.
For AI agents and human developers.

## Checkbox status key

- `- [ ]` open · `- [.]` in progress · `- [#]` not ready

Completed items are moved to [docs/planning/TODO-DONE.md](docs/planning/TODO-DONE.md).

## Bugs


## Tech Debt

- [ ] Refactor runner to only contain public functions.
    - Move all helpers to external includes
    - Abstract directory and file handling to a helper
- [ ] Use INVRT_PLAN_FILE instead of INVRT_CRAWL_FILE where appropriate
- [ ] Remove `array $env` from runPlaywright and use `$this->config` directly.
- [ ] Remove config validation and defaults from runner. All config options should be validated by the config handler which will provide sane defaults. The Runner should assume that any necesary config is correct and present.
- [ ] Remove more business logic from Runner.php and move to the individual ts scripts.
    - Implement `info` as js/info.js
        - Remove the tail of the crawl log
    - Implemenmt configurePlaywright as js/configure-playwright.js
    - 

## Features

### Authentication

- [ ] Replace cookies.json with session.json
    - Add test.use({ storageState: sessin.json })
    - Have playwright wright the session directly to the file during login
 
### User Scripting

#### No-code Testing

- [ ] Create a yaml shorthand for often used testing steps

    ```yaml
    /about.html:
        steps:
            - click .search-trigger
            - snap 'search popup'
            - type 'Test' in .search
            - click 'Search'
            - snap 'search results'
            - click .search-close
    ,,,

### Reporting

- [ ] Create a 1 page html report for all existing test results
- [ ] Create an "Interactive" report
    - [ ] Allow tests to be re-run
    - [ ] Allow differences to be approved
    - [ ] Allow comparison of different environments
    - [ ] Allow comparison of different profiles

### Future Features

- [ ] remove (rm) -- Delete the .invrt directory
- [ ] init --force -
    - Re-init the project even if an .invrt directory exists. 
    - If an INVRT_URL is already defined, use that and don't require a url to be passed.
- [ ] init --unhide
    - Make the invrt directory visible (`invrt` not `.invrt`)
- [ ] --[config-option] override any config option at runtime
- [ ] eg: invrt test --viewport-width=1600

## Documentation
- [ ] Clean up docs
    - Rebuild the app summary to ensure it is complete and correct.
    - Regenerate simple human readable usage documentation.
    - Create in-depth end user documentation for config

## Tests

### Test Cleanup
- [ ] Remove unit tests. Add or update e2e tests for any edge cases that are missing
- [ ] Remove phpstan, phpunit and rector

### End to End Testing
    - [ ] Create a 99 page website which goes 4 levels deep
        Make it look like nice but generic business page. Add Lorum Ipsem test content.

    - [#] Set up ddev to run during tests

        Challenges: Can we use docker-outside-of-docker to control ddev?
        DDev doesn't want to be root but it get's permission errors.

    - [#] Test backdrop support
    - [#] Test drupal auth support

