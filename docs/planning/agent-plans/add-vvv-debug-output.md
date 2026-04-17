# Plan: Add Better -vvv Debug Output

## Goal
Improve CLI diagnostics when commands run with debug verbosity (`-vvv`) while keeping default output unchanged.

## Scope
- Add debug output around command bootstrapping and resolved config context.
- Add debug output around subprocess execution in shared command helpers.
- Add debug output around login subprocess execution.
- Add an E2E assertion for debug output.
- Document verbosity usage in `docs/usage.md`.

## Implementation Steps
1. Add command bootstrap debug output
	- Update `BaseCommand::boot()` to log selected profile/device/environment and resolved key config paths at `VERBOSITY_DEBUG`.
	- Include debug exception detail on config load failure while preserving current failure behavior.

2. Add shared subprocess debug output
	- Update `BaseCommand::runBackstop()` and `BaseCommand::executeScript()` to print command line and exit code at `VERBOSITY_DEBUG`.
	- Keep subprocess stdout/stderr streaming behavior unchanged.

3. Add login flow debug output
	- Update `LoginService::loginIfCredentialsExist()` to log skip reason, login URL, command, output file, and exit code at `VERBOSITY_DEBUG`.
	- Ensure all user-facing success/error lines use explicit verbosity levels.

4. Add E2E coverage for debug mode
	- Extend `tests/e2e/ReferenceCommandTest.php` with a `VERBOSITY_DEBUG` test that asserts debug markers appear in output.
	- Keep existing happy-path assertions intact.

5. Document verbosity usage
	- Add a short section in `docs/usage.md` describing `-v`, `-vv`, and `-vvv` with command examples.

## Acceptance Criteria
- Running commands with default verbosity does not add noisy debug output.
- Running commands with `-vvv` shows clear debug diagnostics for boot/login/subprocess execution.
- Existing command behavior and exit codes remain unchanged.
- E2E test suite includes at least one explicit assertion for `VERBOSITY_DEBUG` output.
- Usage docs explain how to enable debug verbosity.

## Status
- Implementation: complete
- Validation: pending full `task test` run once local PHP/Node dependencies are healthy
