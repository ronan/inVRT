# TODO

Tracks planned features, bugs, test gaps, and tech debt.
For AI agents and human developers.

## Checkbox status key

- `- [ ]` open · `- [.]` in progress · `- [#]` not ready

Completed items are moved to [docs/planning/TODO-DONE.md](docs/planning/TODO-DONE.md).

## Bugs

## Tech Debt

## Tests

### CMS-Specific Testing

  - [#] Set up ddev to run during tests

        Challenges: Can we use docker-outside-of-docker to control ddev?
        DDev doesn't want to be root but it get's permission errors.

  - [#] Test backdrop support
  - [#] Test drupal auth support

## Features

### Advanced flow

## Move to Playwright

## Create plan.yaml

- [ ] Create a plan.yaml (at .invrt/plan.yaml) file during init.
    - The format should be the format specified in [Plan Yaml Spec](docs/planning/proposals/Plan.yaml.specification.md)
    - `plan.yaml` is automatically generated and updated but can be edited by the end user.
    - Modify the following commands to update the Plans.yaml file
        - `invrt init` Adds the base url to the `Project` section
        - `invrt check` Adds the site title and any other discovered information
            - It also adds the base url to the Pages section (as `/: {Home Page Title}`)
 
## Rebuild the Crawler

- [ ] Rewrite the crawler
    - Use the playwright library to:
        - Goto the first page in plan.yaml (initially just the base url of the project)
        - Scrape all links on the page
        - If the page is of type text/html and is not an excluded path add it to plan.yaml
        - Repeat with the next item in plan.yaml
    - [ ] Update the document when new paths are found when crawling with different profiles
- [ ] Rebuild `invrt generate-playwright` and `generate-backstop` to use plan.yaml to create tests


## User Scripting (requires Move to Playwright and Create plan.yaml)

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
- [ ] Future feature: Create a yaml shorthand for often used steps

    ```yaml
    /about.html:
        steps:
            # Automatically run: snap onready
            - click .search-trigger
            - snap 'search popup'
            - type 'Test' in .search
            - click 'Search'
            - snap 'search results'
            - click .search-close
            # Automatically run snap done
    ,,,

### Reporting

- [ ] Create a 1 page html report for all existing test results
- [ ] Create an "Interactive" report
    - [ ] Allow tests to be re-run
    - [ ] Allow differences to be approved
    - [ ] Allow comparison of different environments
    - [ ] Allow comparison of different profiles

### Future Features

- [ ] Better debug output during crawl
- [ ] Rewrite the crawler
    - Make exclude_paths work and provide defaults for drupal/backdrop
    - add a max_width to go with max_depth
  - [ ] Convert crawled-paths.txt to the format in [Plan.md](docs/planning/proposals/Plan.yaml.specification.md)
    - [ ] Name the file 'plan.yaml' and put it at the top of the .invrt directory
    - [ ] Update the document when new paths are found when crawling with different profiles
    - [ ] Turn 'plan.yaml' into 'backstop.json' with backstop test config in it.
- [ ] New flags
  - [ ] --skip-<step>
  - [ ] init --redo (or init --force)
  - [ ] init --unhide 
    - Make the invrt directory visible (`invrt` not `.invrt`)
  - [ ] --[config-option] override any config option at runtime
    - [ ] eg: invrt test --viewport-width=1600

## Documentation
 - [ ] Clean up docs
  - Rebuild the app summary to ensure it is complete and correct.
  - Regenerate simple human readable usage documentation.
  - Create in-depth end user documentation for config


