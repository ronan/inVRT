# inVRT Usage Guide

inVRT is a CLI tool for running Visual Regression Testing (VRT) against CMS-driven websites (Drupal, Backdrop, WordPress). It crawls a site, captures reference screenshots, then detects visual changes on subsequent runs.

---

## Table of Contents

- [inVRT Usage Guide](#invrt-usage-guide)
  - [Table of Contents](#table-of-contents)
  - [Workflow Overview](#workflow-overview)
  - [Commands](#commands)
    - [`init`](#init)
    - [`check`](#check)
    - [`approve`](#approve)
    - [`baseline`](#baseline)
    - [`crawl`](#crawl)
    - [`reference`](#reference)
    - [`test`](#test)
    - [`config`](#config)
    - [`info`](#info)
  - [Options](#options)
  - [Authentication](#authentication)
  - [Data Layout](#data-layout)

---

## Workflow Overview

``` 
invrt init <url>  # One-time setup — creates .invrt/ and saves the site URL
                 # and initializes .invrt/plan.yaml
invrt check       # Verify site connectivity and collect metadata (auto-runs after init)
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
├── plan.yaml             # Auto-generated site plan; safe to edit
├── exclude_urls.txt      # URL path patterns to skip during crawl
├── data/                 # Generated screenshots, reports, logs (gitignore this)
└── scripts/              # Optional user-defined hook scripts
```

The generated `config.yaml` is intentionally minimal. It writes the URL to `environments.<selected-environment>.url` and creates the selected environment, profile, and device keys.

`init` also creates `.invrt/plan.yaml` and seeds `project.url` (and `project.id` when available), plus an initial homepage entry in `pages`.

`init` also creates `.invrt/scripts/onready.ts` with a comment stub. Use that file for root-level page-ready actions that should run before screenshots are captured.

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

### `check`

Verify that the configured site is reachable and collect metadata.

```
invrt check [--environment=<name>] [--profile=<name>] [--device=<name>]
```

Fetches the site homepage, extracts the page title, and merges the result into `.invrt/plan.yaml`.

After a successful check, inVRT updates `.invrt/plan.yaml` with discovered metadata:

- `project.title` is set from the homepage title when available.
- `project.checked_at` records the last check time.
- The configured profiles are listed under top-level `profiles`.
- The homepage entry is ensured under `pages` as `/`.
- Existing user-defined keys in `plan.yaml` are preserved.

`check` runs automatically after `init` and before `crawl` when no plan pages exist yet.

**Output:**

```
✓ Site check complete. Title: "My Site". HTTPS: yes.
```

**plan.yaml after check:**

```yaml
project:
  url: 'https://example.com'
  title: 'My Site'
  checked_at: '2026-04-17T23:38:44+00:00'
profiles:
  - anonymous
exclude:
  - /logout
  - /user/logout
pages:
  /:
    title: 'My Site'
```

**Failure output (unreachable site):**

```
Failed to connect to https://example.com: Connection refused
```

---

### `approve`

Approve the latest test images for the current profile/device/environment combination.

```
invrt approve [--profile=<name>] [--device=<name>] [--environment=<name>]
```

Re-runs Playwright with `--update-snapshots` to promote the latest captured screenshots to the reference baseline.

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

Uses Playwright (Chromium) to load pages from `.invrt/plan.yaml`, extract links, and recursively crawl in-scope HTML pages, respecting `max_crawl_depth` and `max_pages`. The resulting URL list is written to `.invrt/data/<profile>/crawled-paths.text`.

If no config exists yet, `crawl` initializes the project first. When no URL argument or `INVRT_URL` environment variable is available, it prompts for the URL interactively.

If the selected profile has credentials, inVRT logs in first and crawls as the authenticated user (using session cookies).

URLs matching patterns in the `exclude:` list of `.invrt/plan.yaml` are skipped. Default exclusions (used when no list is set): `/user/*`.

As pages are discovered, `crawl` updates `.invrt/plan.yaml`:

- Adds or merges pages into the `pages` tree shape.
- Groups nested paths under common branch prefixes.
- Adds stable page `id` values for discovered testable pages.
- Adds the active profile to each discovered page's `profiles` array.
- Preserves existing user-defined metadata fields.

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
Crawling completed. Found 5 unique paths.
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

Captures a screenshot of each page using Playwright (Chromium). The Playwright config and spec are generated into `.invrt/data/<profile>/` before running, with page tests derived from `.invrt/plan.yaml` (`pages` keys). Screenshots are stored in `.invrt/data/<profile>/<environment>/bitmaps/reference/`.

Each page in `.invrt/plan.yaml` may define optional user hooks that are emitted into the generated Playwright spec:

```yaml
pages:
  /:
    setup: setup.ts
    onready: |
      await page.locator('body').click();
    teardown: |
      console.log('Finished with test');
    /about.html:
      onready: child-ready.js
```

- `setup` runs before `page.goto(...)`.
- `onready` runs after the page reaches `networkidle` and before the screenshot assertion.
- `teardown` runs in a `finally` block after the screenshot step, even if the test fails.
- Hooks defined on a parent page apply to child pages unless the child overrides that hook.
- Values ending in `.js` or `.ts` are loaded from disk. Bare filenames resolve relative to `.invrt/scripts/`. Any other string is treated as inline code.
- `before`, `ready`, and `after` are accepted as aliases for `setup`, `onready`, and `teardown`.

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
Running playwright test -- update-snapshots
```

---

### `test`

Run a visual regression test — compare current screenshots against the reference baseline.

```
invrt test [--profile=<name>] [--device=<name>] [--environment=<name>]
```

Captures fresh screenshots of all crawled URLs and compares them against the reference images using Playwright's snapshot comparison. Generates an HTML report in `.invrt/data/<profile>/report/`.

Before each test run, inVRT regenerates the Playwright spec from `.invrt/plan.yaml` so updated page paths and user hooks are applied even when references already exist.

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
Running playwright test
42 tests ran. 40 passed. 2 failed.
Report: .invrt/data/anonymous/report/index.html
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

### `info`

Display a project status summary.

```
invrt info [--profile=<name>] [--device=<name>] [--environment=<name>]
```

Shows the project name, config file path, the active environment/profile/device, all configured environments/profiles/devices, crawl and screenshot counts for the active combination, and the last few lines of the crawl log.

**Example output:**

```
My Project
/path/to/.invrt/config.yaml

 Active
 Environment  local
 Profile      anonymous
 Device       desktop

 Configured
 Environments  local, staging
 Profiles      anonymous, admin
 Devices       desktop, mobile

 Data
 Crawled pages           42
 Reference screenshots   42
 Test screenshots        42

 Crawl log (last 5 lines)
 ...
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
            └── logs/
                └── crawl.log             # wget crawl log
            └── <device>/
                ├── reference_results.txt     # Output from last `reference` run
                ├── test_results.txt          # Output from last `test` run
                ├── reference/                # Baseline screenshots (`reference` command)
                ├── results/                  # Latest test results (`test` command)
                └── report/                   # Playwright HTML report
```

Add `.invrt/data/` to `.gitignore` to keep generated artifacts out of version control.

---

For a full list of configuration options see [InVRT Configuration](./configuration.md)
