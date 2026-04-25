# TODO

Tracks planned features, bugs, test gaps, and tech debt.
For AI agents and human developers.

## Checkbox status key

- `- [ ]` open · `- [.]` in progress · `- [#]` not ready

Completed items are moved to [docs/planning/TODO-DONE.md](docs/planning/TODO-DONE.md).

## Bugs


## Tech Debt

## Features

### Future Features

- [ ] `invrt remove` (rm) 
    - Delete the .invrt directory. 
    - Ask for confirmation unless the -f/--force flag is passed.
- [ ] `init --force/-f`
    - Re-init the project even if an .invrt directory exists.
    - If an INVRT_URL is defined in the current .invrt config, read that value and don't require the url argument
    - If the url argument is passed in, use that instead of the old value.
    - Delete the contents of .invrt
    - Run the init with the old url or the passed in parameter
- [ ] `init --unhide`
    - When this flag is passed, make the invrt directory visible (`invrt` not `.invrt`)
    - If the command is run when the `.invrt` directory exists, rename the directory but don't change the contents.
- [ ] `init --hide`
    - When this flag is passed, make the invrt directory invisible (`.invrt` not `invrt`)

- [ ] --[config-option] override any config option at runtime
- [ ] eg: invrt test --viewport-width=1600

### Authentication

 
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

