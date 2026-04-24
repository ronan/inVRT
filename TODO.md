# TODO

Tracks planned features, bugs, test gaps, and tech debt.
For AI agents and human developers.

## Checkbox status key

- `- [ ]` open · `- [.]` in progress · `- [#]` not ready

Completed items are moved to [docs/planning/TODO-DONE.md](docs/planning/TODO-DONE.md).

## Bugs


## Tech Debt


## Features

### Authentication

- [ ] Replace cookies.json with session.json
    - Add test.use({ storageState: session.json })
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

### End to End Testing
    - [ ] Create a 99 page website which goes 4 levels deep
        Make it look like nice but generic business page. Add Lorum Ipsem test content.

    - [#] Set up ddev to run during tests

        Challenges: Can we use docker-outside-of-docker to control ddev?
        DDev doesn't want to be root but it get's permission errors.

    - [#] Test backdrop support
    - [#] Test drupal auth support

