# inVRT Application Summary

inVRT is a CLI tool for Visual Regression Testing (VRT) of CMS-driven websites (Drupal, Backdrop, WordPress). It crawls a site, captures baseline screenshots, and detects visual changes on subsequent runs. It supports anonymous and authenticated user flows, multiple environments, and configurable device viewports.

---

## Core Workflow

```
invrt init        # One-time project setup
invrt crawl       # Discover URLs by crawling the site
invrt reference   # Capture baseline screenshots
invrt test        # Compare current screenshots against the baseline
```

Re-run `invrt reference` after intentional visual changes. Re-run `invrt crawl && invrt reference` after structural site changes.

---

## Commands

### `init`
Scaffolds `.invrt/` in the current directory. Creates `config.yaml`, `exclude_urls.txt`, `data/`, and `scripts/`. Fails if already initialized.

### `crawl`
Recursively crawls `INVRT_URL` using `wget`. Outputs a URL list to `.invrt/data/<profile>/<env>/crawled_urls.txt`. Respects `max_crawl_depth` and `max_pages`. Skips patterns in `exclude_urls.txt`. Authenticates first if profile has credentials. Errors if no usable URLs are found (shows last 5 lines of crawl log).

### `reference`
Reads the crawled URL list and captures a Playwright/Chromium screenshot of each page. Stores screenshots in `.invrt/data/<profile>/<env>/bitmaps/reference/`.

### `test`
Captures fresh screenshots and compares them against reference images using BackstopJS (ResembleJS). Outputs an HTML report to `.invrt/data/<profile>/<env>/reports/index.html`. If no reference exists, runs `reference` automatically first. Exits `0` on pass, non-zero on failure.

### `config`
Displays the resolved configuration for the given options. Useful for verifying settings before a run. Prints guidance to run `invrt init` if no config file exists.

---

## Shared Options

All commands except `init` accept:

| Option          | Short | Default     | Description                     |
|-----------------|-------|-------------|---------------------------------|
| `--profile`     | `-p`  | `anonymous` | Profile key from `profiles:`    |
| `--device`      | `-d`  | `desktop`   | Device key from `devices:`      |
| `--environment` | `-e`  | `local`     | Environment key from `environments:` |

Verbosity: `-v` (progress), `-vv` (verbose), `-vvv` (debug + resolved config + subprocess details).

---

## Configuration

Config file: `.invrt/config.yaml`

```yaml
name: My Project

settings:
  url: http://localhost
  viewport_width: 1024
  viewport_height: 768
  max_crawl_depth: 3
  max_pages: 100
  max_concurrent_requests: 5
  login_url: user/login
  user_agent: InVRT/1.0

environments:
  local:
    url: http://localhost
  staging:
    url: https://staging.example.com

profiles:
  anonymous:
    description: Anonymous visitor
  admin:
    username: admin
    password: secret

devices:
  desktop:
    viewport_width: 1024
    viewport_height: 768
  mobile:
    viewport_width: 375
    viewport_height: 667
```

### Config Sections

| Section        | CLI Option              | Default Key |
|----------------|-------------------------|-------------|
| `settings`     | _(base values)_         | n/a         |
| `environments` | `--environment=<key>`   | `local`     |
| `profiles`     | `--profile=<key>`       | `anonymous` |
| `devices`      | `--device=<key>`        | `desktop`   |

### Precedence (lowest → highest)

```
Default → settings → environments.<name> → profiles.<name> → devices.<name> → env var
```

### Configuration Reference

| Config Key                | Env Variable                     | Default      | Commands               |
|---------------------------|----------------------------------|--------------|------------------------|
| `url`                     | `$INVRT_URL`                     | _(empty)_    | crawl, reference, test |
| `login_url`               | `$INVRT_LOGIN_URL`               | _(empty)_    | crawl, reference, test |
| `username`                | `$INVRT_USERNAME`                | _(empty)_    | crawl, reference, test |
| `password`                | `$INVRT_PASSWORD`                | _(empty)_    | crawl, reference, test |
| `viewport_width`          | `$INVRT_VIEWPORT_WIDTH`          | `1024`       | reference, test        |
| `viewport_height`         | `$INVRT_VIEWPORT_HEIGHT`         | `768`        | reference, test        |
| `max_crawl_depth`         | `$INVRT_MAX_CRAWL_DEPTH`         | `3`          | crawl                  |
| `max_pages`               | `$INVRT_MAX_PAGES`               | `100`        | crawl                  |
| `user_agent`              | `$INVRT_USER_AGENT`              | `InVRT/1.0`  | crawl, reference, test |
| `max_concurrent_requests` | `$INVRT_MAX_CONCURRENT_REQUESTS` | `5`          | reference, test        |
| `directory`               | `$INVRT_DIRECTORY`               | `{CWD}/.invrt` | all                  |
| `data_dir`                | `$INVRT_DATA_DIR`                | _(computed)_ | all                    |
| `cookies_file`            | `$INVRT_COOKIES_FILE`            | _(computed)_ | all                    |
| `scripts_dir`             | `$INVRT_SCRIPTS_DIR`             | _(computed)_ | all                    |

---

## Authentication

Triggered when a profile has `username` and `password`. Flow:

1. Playwright navigates to `login_url` (default: `<url>/user/login`) and submits credentials.
2. Session cookies saved as `cookies.json` (Playwright) and `cookies.txt` (Netscape/wget).
3. Subsequent crawl and screenshot steps use these cookies.

Re-login is skipped if cookies files already exist. Delete them to force re-authentication.

---

## Data Layout

```
.invrt/
├── config.yaml
├── exclude_urls.txt
└── data/
    └── <profile>/
        └── <environment>/
            ├── crawled_urls.txt
            ├── cookies.json
            ├── cookies.txt
            ├── bitmaps/
            │   ├── reference/        # Baseline screenshots
            │   └── test/             # Latest test screenshots
            ├── reports/
            │   └── index.html        # BackstopJS comparison report
            └── logs/
                └── crawl.log
```

Add `.invrt/data/` to `.gitignore`.
