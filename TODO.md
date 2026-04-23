# TODO

Tracks planned features, bugs, test gaps, and tech debt.
For AI agents and human developers.

## Checkbox status key

- `- [ ]` open · `- [.]` in progress · `- [#]` not ready

Completed items are moved to [docs/planning/TODO-DONE.md](docs/planning/TODO-DONE.md).

## Bugs

- [x] Exclud path file is not being read.

## Tech Debt

- [x] reduce unnecessary code from php to make test run steps more self contained
- [x] move file generation to js/ts
- [x] Clean up config and get schema generation working again.
  - [x] Remove unnecessary file path config
  - [x] Clean up the schema generator 
  - [x] Teach the ai agents how to edit the schema instead of altering the generated code.

- [x] Standardize output from js/node

## Tests

### CMS-Specific Testing

  - [#] Set up ddev to run during tests

        Challenges: Can we use docker-outside-of-docker to control ddev?
        DDev doesn't want to be root but it get's permission errors.

  - [#] Test backdrop support
  - [#] Test drupal auth support

## Features

### Advanced flow

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


### Move to Playwright

- [ ] Run references and test capture by running the playwright test script

## Create plan.yaml

- [ ] The crawl command should create a structured yaml file in the format specified in [Site Tree Spec](docs/planning/proposals/SITE_TREE_FILE_SPEC.md)

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
- [ ] Future feature: Create a yaml shorthand for often used steps

    ```yaml
    search_popover:
        <url: /about.html
        <steps:
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

- [ ] New flags
  - [ ] --skip-<step>
  - [ ] init --redo (or init --force)
  - [ ] init --unhide 
    - Make the invrt directory visible (`invrt` not `.invrt`)
  - [ ] --[config-option] override any config option at runtime
    - [ ] eg: invrt test --viewport-width=1600
- [ ] Advanced playwright integration
- [ ] Better debug output during crawl
- [ ] Rewrite the crawler
    - Make exclude_paths work and provide defaults for drupal/backdrop
    - add a max_width to go with max_depth
  - [ ] Create a function that converts crawled_urls.txt to the format in SITE_TREE_FILE_SPEC.md
    - [ ] Name the file 'plan.yaml' and put it at the top of the .invrt directory
    - [ ] Update the document when new paths are found when crawling with different profiles
    - [ ] Turn 'plan.yaml' into 'backstop.json' with backstop test config in it.

## Documentation
 - [ ] Clean up docs
  - Rebuild the app summary to ensure it is complete and correct.
  - Regenerate simple human readable usage documentation.
  - Create in-depth end user documentation for config


