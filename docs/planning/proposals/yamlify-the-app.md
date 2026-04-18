# Generate a set of yaml files that define the app

## Goal Summary

Distill [App summart](../../spec/APP_SUMMARY.md) into a set of machine parsable yaml files containing enough information to scaffold out the application being summarized.

The files should be:

docs/planning/spec/Commands.yaml
docs/planning/spec/Configuration.yaml

## Notes:

This is a simple task including only the planning file linked. There is no need to run tests or make additional plans or analyze any codebase. Just create the new files.

## Commands.yml

List each available command along with metadata about how to construct the command programatically.

```yaml
# Commands.yaml

init:
    description: Initialize a new inVRT...
    help: Initializes ...
    login: false
    success: InVRT successfully initialized!
    marker: config.yml
    arguments:
        url:
            description: Website URL to save in the new config file

check:
    description:..
    ...
    marker: check.yml
    requires: [ init ]
    arguments:
        profile:
            description: ...
            short: p
            default: local

crawl:
    ...
    requires: [ init, check ]

```

## Config.yml

A schema (loosely base on json schema but less verbose) for the configuration options including:

- keys, defaults, types, descriptions etc.
- Which get exported to the environment during runtime.
- Which can be added to config.yml
- any other relevent information that is needed to build the app.
