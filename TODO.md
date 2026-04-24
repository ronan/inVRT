# TODO

Tracks planned features, bugs, test gaps, and tech debt.
For AI agents and human developers.

## Checkbox status key

- `- [ ]` open · `- [.]` in progress · `- [#]` not ready

Completed items are moved to [docs/planning/TODO-DONE.md](docs/planning/TODO-DONE.md).

## Bugs

## Tech Debt

## Tests

## Features
 
## Rebuild the Crawler

- [ ] Generate page ids during crawl and add them to plan.yaml
- [ ] Improve the crawler to build a tree-like structure for nested pages.
    - When multiple paths begin with the same prefix and that prefix ends in '/' or '?' they should
    be combined under a parent item whose path is that prefix. 
    - The separating character (/?) should be added to the child items and removed from the parent
    - If the prefix represents a navigable page, add a child with the key `?`, `/` or `` depending on the actual resolved path of the parent page.

## User Scripting

- [ ] Optionally read the onload/onready playwright event script from `INVRT_SCRIPTS_DIR`
- [ ] Allow specification of per-path scripts in plan.yaml
- [ ] Scripts can be a code block on the yaml in typescript or javascript.
- [ ] Allow the user to specify setup/teardown scripts per project
  - [ ] Allow setup/teardown per site section (configured in config.yaml)
  - [ ] " per profile
  - [ ] " per device
  - [ ] " per environment
- [ ] Scripts can be paths to a script in `INVRT_SCRIPTS_DIR`
- [ ] Scripts can be literal codeblock in plan.yaml in javascript or typescript
- [ ] Allow some 'pages' to be functional tests which are not necessarily a URL

## No-code Testing

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

- [ ] New flags
  - [ ] remove (rm) -- Delete the .invrt directory
  - [ ] init --force -- Re-init the project even if an .invrt directory exists
  - [ ] init --unhide
  - [ ] --skip-<step>
    - Make the invrt directory visible (`invrt` not `.invrt`)
  - [ ] --[config-option] override any config option at runtime
    - [ ] eg: invrt test --viewport-width=1600

## Documentation
- [ ] Clean up docs
    - Rebuild the app summary to ensure it is complete and correct.
    - Regenerate simple human readable usage documentation.
    - Create in-depth end user documentation for config

### End to End Testing
    - [ ] Create a 99 page website which goes 4 levels deep
        Make it look like nice but generic business page. Add Lorum Ipsem test content.

    - [#] Set up ddev to run during tests

        Challenges: Can we use docker-outside-of-docker to control ddev?
        DDev doesn't want to be root but it get's permission errors.

    - [#] Test backdrop support
    - [#] Test drupal auth support
