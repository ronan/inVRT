# inVRT Usage Guide

inVRT is a CLI tool for running Visual Regression Testing (VRT) against CMS-driven websites (Drupal, Backdrop, WordPress). It crawls a site, captures reference screenshots, then detects visual changes on subsequent runs.

---

## Table of Contents

- [Installation](#installation)
- [Workflow Overview](#workflow-overview)
- [Commands](#commands)
  - [init](#init)
  - [crawl](#crawl)
  - [reference](#reference)
  - [test](#test)
  - [config](#config)
- [Common Options](#common-options)
- [Configuration](#configuration)
  - [Config File Structure](#config-file-structure)
  - [Environments](#environments)
  - [Profiles](#profiles)
  - [Devices](#devices)
  - [How Config Merging Works](#how-config-merging-works)
- [Authentication](#authentication)
- [Data Layout](#data-layout)

---

## Installation

**Via Composer (global):**

```bash
composer global require ronan/invrt
invrt --version
```

**Via Docker (no install required):**

```bash
docker run -it --volume .:/dir ronan4000/invrt <command>
```

---

## Workflow Overview

```
invrt init        # One-time setup — creates .invrt/ and config.yaml
invrt crawl       # Discover URLs by crawling the site
invrt reference   # Capture baseline screenshots
invrt test        # Compare current screenshots against the baseline
```

A typical CI pipeline runs `crawl → test` on every build, using pre-captured `reference` screenshots as the baseline. Re-run `reference` whenever intentional visual changes are made.

---

## Commands

### `init`

Scaffold a new inVRT project in the current directory.

```
invrt init
```

Creates the following structure:

```
.invrt/
├── config.yaml           # Main configuration — edit this
├── exclude_urls.txt      # URL path patterns to skip during crawl
├── data/                 # Generated screenshots, reports, logs (gitignore this)
└── scripts/              # Optional user-defined hook scripts
```

The generated `config.yaml` includes example environments, profiles, and devices. Edit it before running other commands.

**Output:**

```
🚀 Initializing InVRT for the project at /my/project
✓ Created invrt directory at /my/project/.invrt
✓ Created data directories for generated data, and user scripts.
✓ Initialized InVRT configuration file at /my/project/.invrt/config.yaml
🚀 InVRT successfully initialized!
```

**Error — already initialized:**

```
⚠️  InVRT is already initialized for this project. Please remove the .invrt directory if you want to re-initialize.
```

---

### `crawl`

Crawl the site and build a list of URLs to screenshot.

```
invrt crawl [--profile=<name>] [--device=<name>] [--environment=<name>]
```

Uses `wget` to recursively follow links from `INVRT_URL`, respecting `max_crawl_depth` and `max_pages`. The resulting URL list is written to `.invrt/data/<profile>/<environment>/crawled_urls.txt`.

If the selected profile has credentials, inVRT logs in first and crawls as the authenticated user (using session cookies).

URLs matching patterns in `.invrt/exclude_urls.txt` are skipped. Default exclusions (used when the file doesn't exist): `/files`, `/sites`, `/user/logout`.

**Examples:**

```bash
# Crawl as anonymous user on the local environment
invrt crawl

# Crawl as the admin profile on the dev environment
invrt crawl --profile=admin --environment=dev

# Crawl mobile device profile
invrt crawl --profile=anonymous --device=mobile --environment=prod
```

**Output:**

```
🕸️ Crawling 'local' environment (https://example.com) with profile: 'anonymous' to depth: 3, max pages: 100
No cookie provided. Crawling without authentication.
Crawling finished. Parsing logs for unique urls...
Crawling completed. Results saved to .invrt/data/anonymous/local/crawled_urls.txt
```

---

### `reference`

Capture reference (baseline) screenshots for the current profile/device/environment combination.

```
invrt reference [--profile=<name>] [--device=<name>] [--environment=<name>]
```

Reads the URL list produced by `crawl` and captures a screenshot of each page using Playwright (Chromium). Screenshots are stored in `.invrt/data/<profile>/<environment>/bitmaps/reference/`.

Run this command whenever you make intentional visual changes to establish a new baseline.

**Examples:**

```bash
# Capture desktop references on local
invrt reference

# Capture mobile references on staging
invrt reference --profile=anonymous --device=mobile --environment=staging

# Capture references for authenticated admin
invrt reference --profile=admin --environment=prod
```

**Output:**

```
📸 Capturing references from 'local' environment (https://example.com) with profile: 'anonymous' and device: 'desktop'
[BackstopJS] Reference complete.
```

---

### `test`

Run a visual regression test — compare current screenshots against the reference baseline.

```
invrt test [--profile=<name>] [--device=<name>] [--environment=<name>]
```

Captures fresh screenshots of all crawled URLs and compares them against the reference images using BackstopJS (ResembleJS). Generates an HTML report in `.invrt/data/<profile>/<environment>/reports/`.

Exits with code `0` if all comparisons pass, non-zero if any fail.

**Examples:**

```bash
# Test the default (anonymous/desktop/local) configuration
invrt test

# Test the admin profile on staging
invrt test --profile=admin --environment=staging

# Test mobile and desktop in separate runs
invrt test --device=mobile
invrt test --device=desktop
```

**Output:**

```
🔬 Testing 'local' environment (https://example.com) with profile: 'anonymous' and device: 'desktop'
[BackstopJS] Testing complete. 42 tests run. 40 passed. 2 failed.
[BackstopJS] Report: .invrt/data/anonymous/local/reports/index.html
```

---

### `config`

Display the config values that will be used with the given arguments.

```
invrt config [--profile=<name>] [--device=<name>] [--environment=<name>]
```

Useful for verifying configuration before running tests. If no config file exists, prints a message instructing you to run `invrt init`.

**Examples:**

```bash
invrt config
```

**Output:**

```
# Current inVRT Configuration:
# ============================

name: My Project
environments:
  local:
    name: Local
    url: https://example.com

profiles:
  anonymous:
    name: Anonymous Visitor Profile
    description: Test the site as an anonymous visitor

devices:
  desktop:
    name: Desktop Viewport
    description: Desktop (1920x1080)
    width: 1920
    height: 1080
```

```bash
  invrt config --profile=authenticated --device=mobile
```

**Output:**

```
# Current inVRT Configuration:
# ============================

name: Test Project
environments:
  local:
    name: Local
    url: https://vrt-postdocs-idp-bd.pantheonsite.io

profiles:
  anonymous:
    name: Anonymous Visitor Profile
    description: Test the site as an anonymous visitor

devices:
  desktop:
    name: Desktop Viewport
    description: Desktop (1920x1080)
    width: 1920
    height: 1080
```

---

## Options

All commands except `init` accept these options:

| Option | Shortcut | Default | Description |
|---|---|---|---|
| `--profile` | `-p` | `anonymous` | Profile name defined in `profiles:` |
| `--device` | `-d` | `desktop` | Device name defined in `devices:` |
| `--environment` | `-e` | `local` | Environment name defined in `environments:` |

```bash
invrt crawl -p admin -d mobile -e staging
# equivalent to:
invrt crawl --profile=admin --device=mobile --environment=staging
```

---

## Configuration

### Config File Structure

`.invrt/config.yaml` is the single source of truth for all inVRT settings. The full structure:

```yaml
# .invrt/config.yaml

name: My inVRT Project         # Optional display name

environments:
  local:
    name: Local
    url: http://localhost

  dev:
    name: Development
    url: https://dev.example.com

  prod:
    name: Production
    url: https://prod.example.com

profiles:
  anonymous:
    name: Anonymous Visitor Profile
    description: Test the site as an anonymous visitor with no special permissions.

  admin:
    name: Admin Profile
    description: A profile with admin privileges.
    username: admin
    password: password123
    login_url: https://dev.example.com/user/login   # Optional — defaults to <url>/user/login

devices:
  desktop:
    name: Desktop Viewport
    viewport_width: 1920
    viewport_height: 1080

  mobile:
    name: Mobile Viewport
    viewport_width: 375
    viewport_height: 667
```

### Supported Configuration Keys

Any of these keys can appear under an `environments.<name>`, `profiles.<name>`, or `devices.<name>` block:

| Key | Default | Description |
|---|---|---|
| `url` | _(empty)_ | Base URL to crawl and test |
| `login_url` | `<url>/user/login` | Login page URL (only needed when different from default) |
| `username` | _(empty)_ | Login username — triggers authenticated flow when set |
| `password` | _(empty)_ | Login password |
| `viewport_width` | `1920` | Browser viewport width in pixels |
| `viewport_height` | `1080` | Browser viewport height in pixels |
| `max_crawl_depth` | `3` | Recursion depth for `wget` crawl |
| `max_pages` | `100` | Maximum number of pages to crawl |
| `user_agent` | `InVRT/1.0` | HTTP User-Agent header sent by the crawler |
| `max_concurrent_requests` | `5` | Parallel screenshot captures |

### Environments

Environments represent different deployments of the same site (local, dev, staging, prod). Each typically sets a different `url`.

```yaml
environments:
  local:
    url: http://localhost
  staging:
    url: https://staging.example.com
    username: ci_user
    password: ci_pass
  prod:
    url: https://www.example.com
```

Select with `--environment`:

```bash
invrt crawl --environment=staging
invrt test  --environment=prod
```

### Profiles

Profiles represent different user states — anonymous visitors, editors, admins, etc. A profile with `username` and `password` triggers an authenticated browser session before crawling and screenshotting.

```yaml
profiles:
  anonymous:
    name: Anonymous Visitor

  editor:
    name: Content Editor
    username: editor
    password: editorpass

  admin:
    name: Site Administrator
    username: admin
    password: adminpass
```

Select with `--profile`:

```bash
invrt crawl --profile=editor
invrt test  --profile=admin
```

### Devices

Devices set the browser viewport size, letting you test responsive layouts.

```yaml
devices:
  desktop:
    viewport_width: 1920
    viewport_height: 1080

  tablet:
    viewport_width: 768
    viewport_height: 1024

  mobile:
    viewport_width: 375
    viewport_height: 667
```

Select with `--device`:

```bash
invrt reference --device=mobile
invrt test      --device=mobile
```

### How Config Merging Works

When you run a command, inVRT loads the three named blocks (`environments.<env>`, `profiles.<profile>`, `devices.<device>`) and merges their values. The sections are processed in this order — later sections overwrite earlier ones:

1. `environments.<name>` — applied first (lowest precedence)
2. `profiles.<name>` — applied second, overwrites environment values
3. `devices.<name>` — applied last (highest precedence), overwrites both

Values not present in any block fall back to the defaults in the table above.

**Example:**

```yaml
environments:
  prod:
    url: https://prod.example.com
    viewport_width: 1440

profiles:
  admin:
    username: admin
    password: secret
    viewport_width: 1280

devices:
  mobile:
    viewport_width: 375
    viewport_height: 667
```

Running `invrt test --profile=admin --device=mobile --environment=prod` resolves to:

| Variable | Value | Source |
|---|---|---|
| `INVRT_URL` | `https://prod.example.com` | environment |
| `INVRT_USERNAME` | `admin` | profile |
| `INVRT_PASSWORD` | `secret` | profile |
| `INVRT_VIEWPORT_WIDTH` | `375` | device (overwrites profile's 1280 and environment's 1440) |
| `INVRT_VIEWPORT_HEIGHT` | `667` | device |

All resolved values are exported as `INVRT_*` environment variables and are available to bash and Node.js scripts. If you set any of these in the environment you run the tool in (eg: docker run -e INVRT_DIRECTORY=~/invrt/myapp/.invrt invrt crawl)

#### Full list of exported variables

| Variable | Description |
|---|---|
| `INVRT_PROFILE` | Active profile name |
| `INVRT_DEVICE` | Active device name |
| `INVRT_ENVIRONMENT` | Active environment name |
| `INVRT_DIRECTORY` | Path to `.invrt/` directory |
| `INVRT_DATA_DIR` | `.invrt/data/<profile>/<env>/` |
| `INVRT_COOKIES_FILE` | `.invrt/data/<profile>/<env>/cookies` |
| `INVRT_CONFIG_FILE` | Path to `config.yaml` |
| `INVRT_SCRIPTS_DIR` | Path to the inVRT `src/` scripts directory |
| `INIT_CWD` | Working directory where invrt was invoked |
| `INVRT_URL` | Resolved base URL |
| `INVRT_LOGIN_URL` | Resolved login URL |
| `INVRT_USERNAME` | Resolved username |
| `INVRT_PASSWORD` | Resolved password |
| `INVRT_VIEWPORT_WIDTH` | Resolved viewport width |
| `INVRT_VIEWPORT_HEIGHT` | Resolved viewport height |
| `INVRT_MAX_CRAWL_DEPTH` | Resolved crawl depth |
| `INVRT_MAX_PAGES` | Resolved max pages |
| `INVRT_USER_AGENT` | Resolved User-Agent string |
| `INVRT_MAX_CONCURRENT_REQUESTS` | Resolved concurrency limit |

---

## Authentication

When a profile has `username` and `password` set, inVRT runs an authenticated flow before crawling or screenshotting:

1. A Playwright browser session navigates to `login_url` (defaults to `<url>/user/login`).
2. Credentials are submitted. Session cookies are saved to `<INVRT_COOKIES_FILE>.json`.
3. Cookies are converted to Netscape format (`<INVRT_COOKIES_FILE>.txt`) for use by `wget`.
4. Subsequent crawl and screenshot steps use these cookies.

**Re-login is skipped** if a cookies file already exists for the active profile/environment combination. Delete the file to force a fresh login:

```bash
rm .invrt/data/admin/local/cookies.json
rm .invrt/data/admin/local/cookies.txt
```

**Custom login URL:**

```yaml
profiles:
  admin:
    username: admin
    password: secret
    login_url: https://example.com/admin/login   # override the default /user/login path
```

---

## Data Layout

inVRT stores all generated data under `.invrt/data/`, namespaced by profile and environment so that each user profile can test a different set of site paths:

```
.invrt/
└── data/
    └── <profile>/
        └── <environment>/
            ├── crawled_urls.txt          # URL list produced by `crawl`
            ├── cookies.json              # Playwright session cookies
            ├── cookies.txt               # Netscape-format cookies for wget
            ├── bitmaps/
            │   ├── reference/            # Baseline screenshots (`reference` command)
            │   └── test/                 # Latest test screenshots (`test` command)
            ├── reports/
            │   └── index.html            # BackstopJS comparison report
            └── logs/
                └── crawl.log             # wget crawl log
```

Add `.invrt/data/` to `.gitignore` to keep generated artifacts out of version control.
