# inVRT Usage Guide

inVRT is a CLI tool for running Visual Regression Testing (VRT) against CMS-driven websites (Drupal, Backdrop, WordPress). It crawls a site, captures reference screenshots, then detects visual changes on subsequent runs.

---

## Table of Contents

- [inVRT Usage Guide](#invrt-usage-guide)
  - [Table of Contents](#table-of-contents)
  - [Workflow Overview](#workflow-overview)
  - [Commands](#commands)
    - [`init`](#init)
    - [`approve`](#approve)
    - [`baseline`](#baseline)
    - [`crawl`](#crawl)
    - [`reference`](#reference)
    - [`test`](#test)
    - [`config`](#config)
  - [Options](#options)
  - [Authentication](#authentication)
  - [Data Layout](#data-layout)

---

## Workflow Overview

``` 
invrt init <url>  # One-time setup — creates .invrt/ and saves the site URL
invrt crawl       # Discover URLs by crawling the site
invrt reference   # Capture baseline screenshots
invrt test        # Compare current screenshots against the baseline
invrt approve     # Promote the latest test screenshots to the approved baseline
invrt baseline    # Ensure reference + test exist, then approve them
```

Run `invrt crawl && invrt reference` to discover the site structure  and establish a visual baseline.

Run `invty test` on every build to discover visual chanegs.

Re-run `invrt reference` whenever intentional visual changes are made.

Re-run `invrt crawl && invrt reference` whenever intentional site structire changes are made.

---

## Commands

### `init`

Scaffold a new inVRT project in the current directory.

```
invrt init <url> [--environment=<name>] [--profile=<name>] [--device=<name>]
```

Creates the following structure:

```
.invrt/
├── config.yaml           # Main configuration — edit this
├── exclude_urls.txt      # URL path patterns to skip during crawl
├── data/                 # Generated screenshots, reports, logs (gitignore this)
└── scripts/              # Optional user-defined hook scripts
```

The generated `config.yaml` is intentionally minimal. It writes the URL to `environments.<selected-environment>.url` and creates the selected environment, profile, and device keys.

If you omit the URL argument in an interactive terminal, inVRT prompts for it.

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

### `approve`

Approve the latest test images for the current profile/device/environment combination.

```
invrt approve [--profile=<name>] [--device=<name>] [--environment=<name>]
```

This runs `backstop approve` against the current capture directory.

### `baseline`

Create or refresh an approved baseline in one command.

```
invrt baseline [--profile=<name>] [--device=<name>] [--environment=<name>]
```

If reference screenshots are missing, `baseline` runs `reference`. If test screenshots are missing, it runs `test`. It then runs `approve`.

---

### `crawl`

Crawl the site and build a list of URLs to screenshot.

```
invrt crawl [--profile=<name>] [--device=<name>] [--environment=<name>]
```

Uses `wget` to recursively follow links from `INVRT_URL`, respecting `max_crawl_depth` and `max_pages`. The resulting URL list is written to `.invrt/data/<profile>/<environment>/crawled_urls.txt`.

If no config exists yet, `crawl` initializes the project first. When no URL argument or `INVRT_URL` environment variable is available, it prompts for the URL interactively.

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
Crawling completed. Found 5 unique paths. Results saved to .invrt/data/anonymous/local/crawled_urls.txt
```

**Failure output (no usable URLs):**

```
No usable URLs were found during crawl. See crawl log details below:
Last 5 lines of crawl log:
failed: Connection refused.
Giving up.
```

---

### `reference`

Capture reference (baseline) screenshots for the current profile/device/environment combination.

```
invrt reference [--profile=<name>] [--device=<name>] [--environment=<name>]
```

Reads the URL list produced by `crawl` and captures a screenshot of each page using Playwright (Chromium). Screenshots are stored in `.invrt/data/<profile>/<environment>/bitmaps/reference/`.

If no `crawled_urls.txt` file exists for the current profile/device/environment combination, `crawl` is run automatically before capturing screenshots.

If no config exists yet, `reference` initializes the project first, then continues with the normal first-run flow.

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

If no reference screenshots exist for the current profile/device/environment combination, the
reference step is run automatically before the test. If crawled URLs are also missing, `reference`
automatically runs `crawl` first.

If no config exists yet, `test` initializes the project first, then continues through the normal first-run chain.

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
    description: Small Desktop/Tablet (1024x768)
    viewport_width: 1024
    viewport_height: 768
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
    url: https://vrt-postdocs-idp-bd.pantheonsite.io

profiles:
  anonymous:
    description: Test the site as an anonymous visitor

devices:
  desktop:
    description: Small Desktop/Tablet (1024x768)
    viewport_width: 1024
    viewport_height: 768
```

---

## Options

All commands except `init` accept these options to alter which config values are used during the command run:

| Option          | Shortcut | Default     | Description                                 |
| --------------- | -------- | ----------- | ------------------------------------------- |
| `--profile`     | `-p`     | `anonymous` | Profile name defined in `profiles:`         |
| `--device`      | `-d`     | `desktop`   | Device name defined in `devices:`           |
| `--environment` | `-e`     | `local`     | Environment name defined in `environments:` |

```bash
invrt crawl -p admin -d mobile -e staging
# equivalent to:
invrt crawl --profile=admin --device=mobile --environment=staging
```

### Output verbosity

inVRT uses Symfony Console verbosity levels. Add `-v`, `-vv`, or `-vvv` to any command to increase output detail.

- `-v`: extra progress details.
- `-vv`: very verbose command details.
- `-vvv`: debug-level output, including resolved configuration context and subprocess command/exit details.

```bash
invrt crawl -vvv
invrt reference --profile=admin --environment=dev -vvv
invrt test -vvv
```

---

## Authentication

When a configured project has `username` and `password` set, inVRT runs an authenticated flow before crawling or screenshotting:

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

---

For a full list of configuration options see [InVRT Configuration](./configuration.md)
