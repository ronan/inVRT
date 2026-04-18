# inVRT Application Summary

inVRT is a Symfony Console CLI for visual regression testing of CMS-driven sites. It can initialize a project, check site reachability, crawl pages with `wget`, log in with Playwright when credentials are configured, capture reference screenshots with BackstopJS/Playwright, run visual comparisons, and approve a new baseline.

---

## Core Workflow

```bash
invrt init <url>  # Create .invrt/ and seed config for the selected env/profile/device
invrt check       # Fetch homepage metadata and write check.yaml
invrt crawl       # Crawl the configured site and save discovered paths
invrt reference   # Capture reference screenshots from the crawled paths
invrt test        # Capture fresh screenshots and compare against reference
invrt approve     # Approve the latest BackstopJS test results
invrt baseline    # Ensure reference + test artifacts exist, then approve
invrt info        # Show current project status for the selected context
invrt config      # Print the resolved INVRT_* configuration
```

`crawl`, `reference`, `test`, and `baseline` auto-initialize the project when `.invrt/config.yaml` is missing. `reference` runs `crawl` first when no crawl file exists. `test` runs `reference` first when no approved reference exists. `baseline` runs whichever prerequisite steps are still missing before approving.

---

## Commands

### `init`

Initializes a new inVRT project in the current working directory. Creates:

- `.invrt/config.yaml`
- `.invrt/data/`
- `.invrt/scripts/`
- `.invrt/exclude_paths.txt`

It writes a minimal config with the currently selected environment/profile/device keys and stores the provided URL at `environments.<selected-environment>.url`. If no URL argument is provided and stdin is interactive, it prompts for one. It fails if `.invrt/` already exists. After initialization it runs `check`, warning if the site is not reachable yet.

### `check`

Fetches the configured site URL, follows redirects, extracts the page title, detects whether the resolved URL uses HTTPS, and records any permanent redirect source. Writes the result to `INVRT_CHECK_FILE`, which defaults to `.invrt/data/<environment>/check.yaml`.

The written YAML may include:

- `url`
- `title`
- `https`
- `redirected_from`
- `checked_at`

`check` does not attempt login.

### `crawl`

Crawls `INVRT_URL` with `wget`, scoped to the current environment/profile. It:

- runs `check` first if `check.yaml` does not exist
- writes wget stderr output to `INVRT_CRAWL_LOG`
- parses discovered paths from the crawl log
- writes sorted unique paths to `INVRT_CRAWL_FILE`
- optionally authenticates first when the selected profile has credentials

Crawling uses:

- `INVRT_MAX_CRAWL_DEPTH`
- `INVRT_MAX_PAGES`
- `INVRT_EXCLUDE_FILE`
- either `INVRT_COOKIE` as a raw `Cookie:` header or `INVRT_COOKIES_FILE.txt` as a Netscape cookie jar

If no usable URLs are found, it fails and prints the tail of the crawl log.

### `reference`

Captures reference screenshots with BackstopJS/Playwright for the current environment/profile/device. If the crawl file is missing, it runs `crawl` first. It fails when the crawl file exists but contains no usable URLs.

Artifacts are written beneath `INVRT_CAPTURE_DIR`, including:

- `bitmaps/reference/`
- `reports/html/`
- `reports/json/`
- `reports/ci/`
- `backstop-config.json`
- `reference_results.txt`

### `test`

Runs visual regression comparison with BackstopJS. If `INVRT_REFERENCE_RESULTS_FILE` is missing, it runs `reference` first. Test output is written under `INVRT_CAPTURE_DIR`, including `bitmaps/test/`, reports, and `test_results.txt`.

Exit code is BackstopJS' exit code: `0` on pass, non-zero on failure.

### `approve`

Runs BackstopJS `approve` for the selected environment/profile/device. It requires a config file but does not attempt login first.

### `baseline`

Refreshes the approved baseline. It runs:

1. `reference` if reference results are missing
2. `test` if test results are missing
3. `approve`

### `info`

Displays a status summary for the selected environment/profile/device:

- project name
- config file path
- selected environment/profile/device
- configured environment/profile/device keys
- crawled page count
- reference screenshot count
- test screenshot count
- last 5 lines of the crawl log, when available

`info` does not attempt login.

### `config`

Prints the resolved flat `INVRT_*` configuration for the selected environment/profile/device. It requires a config file and does not attempt login.

---

## Shared Options

All commands use the same selection options. `init` adds an optional positional URL argument.

| Option | Short | Default | Meaning |
|---|---|---|---|
| `--environment` | `-e` | `local` | Key in `environments:` |
| `--profile` | `-p` | `anonymous` | Key in `profiles:` |
| `--device` | `-d` | `desktop` | Key in `devices:` |

The command boot layer also honors existing `INVRT_*` process environment variables. `INVRT_CONFIG_FILE` overrides config file discovery completely. Otherwise the default config path is:

```text
<INVRT_DIRECTORY>/config.yaml
```

or, when `INVRT_DIRECTORY` is unset:

```text
<INVRT_CWD>/.invrt/config.yaml
```

---

## Configuration

Config file: `.invrt/config.yaml`

Typical shape:

```yaml
name: My Project

settings:
  url: http://localhost
  login_url: http://localhost/user/login
  username: ""
  password: ""
  cookie: ""
  max_crawl_depth: 3
  max_pages: 100
  user_agent: InVRT/1.0
  viewport_width: 1024
  viewport_height: 768

environments:
  local:
    url: http://localhost

profiles:
  anonymous: {}
  admin:
    username: admin
    password: secret

devices:
  desktop: {}
  mobile:
    viewport_width: 375
    viewport_height: 667
```

### Sections

| Section | Purpose |
|---|---|
| `name` | Human-readable project name shown by `info` |
| `settings` | Base values shared by all environments, profiles, and devices |
| `environments.<key>` | Environment-specific overrides such as `url` |
| `profiles.<key>` | Profile-specific overrides such as credentials or cookies |
| `devices.<key>` | Device-specific overrides such as viewport size |

### Resolution and Precedence

Configuration resolves to a flat `INVRT_*` map in this precedence order, highest first:

1. explicit `INVRT_*` environment variables passed into the process, including selected `INVRT_PROFILE`, `INVRT_ENVIRONMENT`, and `INVRT_DEVICE`
2. `devices.<selected-device>`
3. `profiles.<selected-profile>`
4. `environments.<selected-environment>`
5. `settings`
6. hard-coded defaults

Bare YAML keys are converted to `INVRT_*` names. String values are interpolated against other `INVRT_*` values, allowing computed paths like `INVRT_DIRECTORY/data/INVRT_ENVIRONMENT/INVRT_PROFILE`.

### Supported Settings Keys

These keys are accepted in `settings`, `environments.*`, `profiles.*`, and `devices.*`:

| YAML key | Resolved env var | Default |
|---|---|---|
| `url` | `INVRT_URL` | `""` |
| `login_url` | `INVRT_LOGIN_URL` | `""` |
| `username` | `INVRT_USERNAME` | `""` |
| `password` | `INVRT_PASSWORD` | `""` |
| `cookie` | `INVRT_COOKIE` | `""` |
| `max_crawl_depth` | `INVRT_MAX_CRAWL_DEPTH` | `3` |
| `max_pages` | `INVRT_MAX_PAGES` | `100` |
| `user_agent` | `INVRT_USER_AGENT` | `InVRT/1.0` |
| `viewport_width` | `INVRT_VIEWPORT_WIDTH` | `1024` |
| `viewport_height` | `INVRT_VIEWPORT_HEIGHT` | `768` |
| `directory` | `INVRT_DIRECTORY` | `INVRT_CWD/.invrt` |
| `config_file` | `INVRT_CONFIG_FILE` | `INVRT_DIRECTORY/config.yaml` |
| `scripts_dir` | `INVRT_SCRIPTS_DIR` | `INVRT_DIRECTORY/scripts` |
| `crawl_dir` | `INVRT_CRAWL_DIR` | `INVRT_DIRECTORY/data/INVRT_ENVIRONMENT/INVRT_PROFILE` |
| `cookies_file` | `INVRT_COOKIES_FILE` | `INVRT_CRAWL_DIR/cookies` |
| `crawl_log` | `INVRT_CRAWL_LOG` | `INVRT_CRAWL_DIR/logs/crawl.log` |
| `clone_dir` | `INVRT_CLONE_DIR` | `INVRT_CRAWL_DIR/clone` |
| `crawl_file` | `INVRT_CRAWL_FILE` | `INVRT_CRAWL_DIR/crawled_urls.txt` |
| `exclude_file` | `INVRT_EXCLUDE_FILE` | `INVRT_CRAWL_DIR/exclude_paths.txt` |
| `capture_dir` | `INVRT_CAPTURE_DIR` | `INVRT_DIRECTORY/data/INVRT_ENVIRONMENT/INVRT_PROFILE/INVRT_DEVICE` |
| `check_file` | `INVRT_CHECK_FILE` | `INVRT_DIRECTORY/data/INVRT_ENVIRONMENT/check.yaml` |
| `reference_results_file` | `INVRT_REFERENCE_RESULTS_FILE` | `INVRT_CAPTURE_DIR/reference_results.txt` |
| `test_results_file` | `INVRT_TEST_RESULTS_FILE` | `INVRT_CAPTURE_DIR/test_results.txt` |

Additional resolved context values always present at runtime:

| Env var | Meaning |
|---|---|
| `INVRT_CWD` | Current working directory used for default path resolution |
| `INVRT_ENVIRONMENT` | Selected environment key |
| `INVRT_PROFILE` | Selected profile key |
| `INVRT_DEVICE` | Selected device key |

---

## Authentication

Login is triggered automatically before `crawl`, `reference`, `test`, and `baseline` when either `username` or `password` is configured for the selected profile.

Flow:

1. Resolve `login_url`, defaulting to `<url>/user/login` when not set.
2. Run `src/js/playwright-login.js`.
3. Save cookies to `INVRT_COOKIES_FILE.json`.
4. Convert that file to `INVRT_COOKIES_FILE.txt` in Netscape format for `wget`.

`approve`, `check`, `config`, `info`, and `init` do not auto-login.

For crawling, `INVRT_COOKIE` takes precedence over the cookie jar and is sent as a raw `Cookie:` header.

---

## Screenshot Engine

`src/js/backstop.js` builds a BackstopJS config from the resolved `INVRT_*` environment:

- viewport comes from `INVRT_VIEWPORT_WIDTH` and `INVRT_VIEWPORT_HEIGHT`
- scenarios come from the newline-delimited paths in `INVRT_CRAWL_FILE`
- each scenario URL is `INVRT_URL + <path>`
- scenario labels are normalized from the path and suffixed with a SHA-1 hash for stability
- only the first `INVRT_MAX_PAGES` crawl entries are used

Hook scripts are resolved from `INVRT_SCRIPTS_DIR` when both of these files exist there:

- `playwright-onbefore.js`
- `playwright-onready.js`

Otherwise the built-in scripts from `src/js/` are used.

The built-in hooks:

- load cookies from `scenario.cookiePath` before each page
- record `pageTitle`
- optionally perform hover, click, keypress, post-interaction wait, and scroll interactions when scenario fields define them

---

## Data Layout

```text
.invrt/
├── config.yaml
├── exclude_paths.txt
├── scripts/
└── data/
    └── <environment>/
        ├── check.yaml
        └── <profile>/
            ├── cookies.json
            ├── cookies.txt
            ├── crawled_urls.txt
            ├── exclude_paths.txt
            ├── clone/
            ├── logs/
            │   └── crawl.log
            └── <device>/
                ├── backstop-config.json
                ├── reference_results.txt
                ├── test_results.txt
                ├── bitmaps/
                │   ├── reference/
                │   └── test/
                └── reports/
                    ├── ci/
                    ├── html/
                    └── json/
```

Note that `init` seeds `.invrt/exclude_paths.txt`, while the default resolved `exclude_file` path is `.invrt/data/<environment>/<profile>/exclude_paths.txt` unless overridden in config.
