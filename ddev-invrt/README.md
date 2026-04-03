[![add-on registry](https://img.shields.io/badge/DDEV-Add--on_Registry-blue)](https://addons.ddev.com)
[![tests](https://github.com/ronan/ddev-invrt/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/ronan/ddev-invrt/actions/workflows/tests.yml?query=branch%3Amain)
[![last commit](https://img.shields.io/github/last-commit/ronan/ddev-invrt)](https://github.com/ronan/ddev-invrt/commits)
[![release](https://img.shields.io/github/v/release/ronan/ddev-invrt)](https://github.com/ronan/ddev-invrt/releases/latest)

# DDEV Invrt

## Overview

This add-on integrates [InVRT](https://github.com/ronan/invrt) into your [DDEV](https://ddev.com/) project.

## Installation

```bash
ddev add-on get ronan/ddev-invrt
ddev restart
```

After installation, the addon will automatically configure InVRT for your project and capture a set of baseline images.

For Drupal sites, the addon will automatically configure

    - A test profile for each role
    - A test device for each theme breakpoint
    - (Pantheon) Environment config for dev/test/live

## Commands

If you update your project type, theme or user types, you can reconfigure InVRT:

        `ddev invrt-autoconfigure`

If your site structure or content changes, you can capture a new baseline by running:

        `ddev invrt baseline`

To test the ddev project against the baseline, run:

        `ddev invrt test`

To view the test results report run:

        `ddev invrt report`


## Usage

| Command | Description |
| ------- | ----------- |
| `ddev invrt test` | Test the current site against the captured baseline |
| `ddev invrt baseline` | Crawl the site and capture a set of baseline images |
| `ddev invrt autoconfigure` | Automatically (re)configure testing based on the current DDEV project |



## Credits

**Contributed and maintained by [@ronan](https://github.com/ronan)**
**Based on Backstop.js by [@garris](https://github.com/garris/backstopjs)**
