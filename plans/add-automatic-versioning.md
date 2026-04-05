# Plan: Add automatic versioning

## Todo item
- Add automatic versioning.
- Use semantic versioning.
- Create a `version:bump` task that:
  - bumps to next patch version,
  - updates documentation,
  - builds/tags/publishes Docker image,
  - builds/publishes `ddev-invrt` addon.

## Goal
Provide a repeatable, mostly-automated release workflow for patch releases using one command.

## Short implementation plan
1. Locate current single source of truth for project version and define the canonical release version location.
2. Add a Node script in `tooling/scripts/` to bump patch version safely and sync all required version locations/files.
3. Add Taskfile tasks for `version:bump` and `release:prepare` that run version bump + local validation (`task test`).
4. Add non-interactive release tasks for Docker and `ddev-invrt` publishing that require needed env vars/tokens and fail fast if missing.
5. Document the exact release flow and required credentials in usage/development docs, with examples.
6. Add focused tests for the bump script behavior (happy path + invalid version/file state).

## Acceptance checks
- Running `task version:bump` updates patch version consistently.
- Docs reflect updated version and release procedure.
- Release tasks are callable, explicit, and validate required credentials before publish steps.
- Existing test/lint workflow still passes.
