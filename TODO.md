# TODO

Tracks planned features, bugs, test gaps, and tech debt.
For AI agents and human developers.

## Checkbox status key

- `- [ ]` open · `- [.]` in progress · `- [#]` not ready

Completed items are moved to [docs/planning/TODO-DONE.md](docs/planning/TODO-DONE.md).

## Tests

### CMS-Specific Testing

  - [#] Set up ddev to run during tests

        Challenges: Can we use docker-outside-of-docker to control ddev?
        DDev doesn't want to be root but it get's permission errors.

  - [#] Test backdrop support
  - [#] Test drupal auth support

## Features

### Advanced flow
- [.] Implement `invrt check` to load the homepage and retrieve the site title
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

