# inVRT Application Summary

inVRT is a Symfony Console CLI for visual regression testing of CMS-driven sites. It can initialize a project, check site reachability, crawl pages with `wget`, log in with Playwright when credentials are configured, capture reference screenshots with Playwright, run visual comparisons, and approve a new baseline.

---

## Core Workflow

```bash
invrt init <url>  # Create .invrt/ and seed config for the selected env/profile/device
                 # Also create .invrt/plan.yaml
invrt check       # Fetch homepage metadata and write check.yaml
invrt crawl       # Crawl the configured site and save discovered paths
invrt reference   # Capture reference screenshots from the crawled paths
invrt test        # Capture fresh screenshots and compare against reference
invrt approve     # Re-run Playwright with --update-snapshots to promote latest screenshots
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

`init` also creates `.invrt/plan.yaml` and seeds:

- `project.url` from the initialized URL
- `project.id` from resolved project ID when available
- `pages['/']` as the initial homepage entry

During init, inVRT also generates a stable project identifier and stores it at `settings.id`. The ID is derived from the URL hostname plus a random seed so projects with the same URL still get distinct report IDs.

### `check`

Fetches the configured site URL, follows redirects, extracts the page title, detects whether the resolved URL uses HTTPS, and records any permanent redirect source. Writes the result to `INVRT_CHECK_FILE`, which defaults to `.invrt/data/<environment>/check.yaml`.

After writing `check.yaml`, `check` updates `INVRT_PLAN_FILE` (default `.invrt/plan.yaml`) by merging discovered metadata without removing user-defined keys:

- updates `project.title` when title is discovered
- ensures homepage exists in `pages` as `/`

The written YAML may include:

- `url`
- `title`
- `https`
- `redirected_from`
- `checked_at`

`check` does not attempt login.

### `crawl`

Crawls `INVRT_URL` with Playwright Chromium, scoped to the current environment/profile. It:

- runs `check` first if `check.yaml` does not exist
- starts from paths in `INVRT_PLAN_FILE` (falls back to `/`)
- visits same-origin pages, accepts `text/html` responses, and extracts `a[href]` links
- respects `INVRT_MAX_CRAWL_DEPTH`, `INVRT_MAX_PAGES`, and `INVRT_EXCLUDE_FILE`
- writes crawl progress/errors to `INVRT_CRAWL_LOG`
- writes sorted unique paths to `INVRT_CRAWL_FILE`
- merges discovered paths into `INVRT_PLAN_FILE` using a tree-style `pages` structure
- generates stable page IDs and updates each discovered page with active-profile access in `profiles`
- preserves existing page metadata where possible while updating managed crawl fields
- generates BackstopJS config to `INVRT_BACKSTOP_CONFIG_FILE` after a successful crawl

If no usable URLs are found, it fails and prints the tail of the crawl log.

### `reference`

Captures reference screenshots using Playwright for the current environment/profile/device. If the crawl file is missing, it runs `crawl` first. It fails when the crawl file exists but contains no usable URLs.

Before capturing, it runs `generate-playwright` (which in turn runs `configure-playwright`) to write `playwright.config.ts` and the device spec to `INVRT_CRAWL_DIR`. `generate-playwright` derives scenarios from `INVRT_PLAN_FILE` (`plan.yaml` `pages` keys). It then runs `npx playwright test --update-snapshots`.

Artifacts are written to `INVRT_CRAWL_DIR`:

- `reference/` — snapshot PNG files (Playwright snapshot dir)
- `results/` — Playwright test results
- `report/` — Playwright HTML report
- `reference_results.txt`

### `test`

Runs visual regression comparison with Playwright. If `INVRT_REFERENCE_FILE` is missing, it runs `reference` first. Runs `npx playwright test` (no `--update-snapshots`). Test output is written to `INVRT_CRAWL_DIR/results/` and `report/`, and `test_results.txt`.

Exit code is Playwright's exit code: `0` on pass, non-zero on failure.

### `approve`

Re-runs Playwright with `--update-snapshots` for the selected environment/profile/device, promoting the latest screenshots to the reference baseline. Does not attempt login first.

### `baseline`

Refreshes the approved baseline from scratch:

1. `check`
2. `crawl`
3. `generate-playwright` (includes `configure-playwright`)
4. Playwright `reference` (`--update-snapshots`)
5. Playwright `test`
6. Playwright `reference` again (approve — update snapshots to match test results)

### `info`

Displays a status summary for the selected environment/profile/device:

- project name
- project ID (`settings.id` / `INVRT_ID`)
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
| `id` | `INVRT_ID` | `""` |
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
| `backstop_config_file` | `INVRT_BACKSTOP_CONFIG_FILE` | `INVRT_CAPTURE_DIR/backstop-config.json` |
| `check_file` | `INVRT_CHECK_FILE` | `INVRT_DIRECTORY/data/INVRT_ENVIRONMENT/check.yaml` |
| `plan_file` | `INVRT_PLAN_FILE` | `INVRT_DIRECTORY/plan.yaml` |
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

Config generation and BackstopJS execution are separated into two scripts:

### Config generation (`src/js/backstop-config.js`)

`src/js/backstop-config.js` builds a BackstopJS config from the resolved `INVRT_*` environment and writes it to `INVRT_BACKSTOP_CONFIG_FILE`:

- viewport comes from `INVRT_VIEWPORT_WIDTH` and `INVRT_VIEWPORT_HEIGHT`
- scenarios come from the newline-delimited paths in `INVRT_CRAWL_FILE`
- each scenario URL is `INVRT_URL + <path>`
- scenario labels are normalized from the path and suffixed with a SHA-1 hash for stability
- only the first `INVRT_MAX_PAGES` crawl entries are used

Config generation runs automatically at the end of a successful `crawl`. If the config file is missing when `reference` or `test` starts, it is regenerated before BackstopJS runs.

### BackstopJS runner (`src/js/backstop.js`)

`src/js/backstop.js` loads the pre-generated config from `INVRT_BACKSTOP_CONFIG_FILE` and runs the requested BackstopJS operation (reference, test, or approve).

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
├── plan.yaml
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
