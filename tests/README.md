# inVRT Test Suite

The test suite is split into:

- `tests/Unit/` — PHPUnit unit tests for PHP services in isolation
- `tests/bats/` — Bats end-to-end tests that execute the real `bin/invrt` CLI
- `tests/fixtures/website/` — static website served by a local PHP webserver during Bats workflow tests

## Running Tests

```bash
task test
task test:php:unit
task test:bats
task test:bats:docker
```

`task test:bats` runs the CLI suite on the host.

`task test:bats:docker` builds the dedicated Docker `test` stage and runs the same suite inside the Docker-defined environment.

## Bats Fixtures and Artifacts

The Bats suite creates one artifact directory per test and clears that directory at the start of the test.

- Preferred runtime path: `/scratch/tests/`
- Host fallback when `/scratch/tests/` is unavailable: `scratch/tests/`
- Docker task mount: `scratch/tests/` on the host is mounted to `/scratch/tests/` in the container

Artifacts are intentionally preserved for inspection, including:

- generated `.invrt/` project data
- command stdout/stderr logs
- temporary stdin runner scripts
- PHP fixture webserver logs and pid/port metadata

## Notes

- Interactive CLI flows are exercised through a pseudo-TTY using `script`.
- Workflow tests use the real PHP built-in server, `wget`, Playwright, and BackstopJS.
- `tooling/config/phpunit.xml` now covers only PHPUnit unit tests.
