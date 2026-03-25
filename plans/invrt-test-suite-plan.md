# Plan: Better invrt Test Suite

## Overview
Expand test coverage from 52 tests to ~100 tests across 4 test categories. Add comprehensive E2E command tests, error handling, and service layer tests. Use real bash script execution to test actual CLI workflows end-to-end.

## Current State
- **Tests:** 52 tests across Unit/ and E2E/ directories
- **Coverage:** Utility functions (getConfig, joinPath, convertCookies), argument parsing, YAML config loading
- **Gaps:** CLI commands (init, crawl, reference, test, config), error scenarios, real bash script execution, Environment variables, service interactions

## Architecture Context
- Framework: Symfony Console with BaseCommand pattern
- Delegation: Commands pass environment to bash scripts for execution
- Services: EnvironmentService handles profile/environment resolution
- Testing Tools: PHPUnit 9.5+, Symfony/Yaml, fixture files

---

## Implementation Plan

### Phase 1: E2E Command Tests (~20 tests)

Create `tests/E2E/` directory with end-to-end tests that execute real bash scripts.

**Base Infrastructure:**
- `tests/E2E/CommandTestCase.php` — abstract base class providing:
  - Temporary project directory setup/teardown
  - Fixture configuration file creation
  - Real bash script invocation
  - Output/exit code capture
  - Environment variable helpers

**Command Tests (5 tests, one for each command):**
- `tests/E2E/InitCommandTest.php` — InitCommand behavior:
  - Creates project structure when run
  - Generates valid config files
  - Successful exit code

- `tests/E2E/CrawlCommandTest.php` — CrawlCommand behavior:
  - Loads config from project
  - Resolves profile/environment/device
  - Sets environment variables correctly
  - Invokes invrt-crawl.sh with proper env
  - Handles missing config gracefully

- `tests/E2E/ReferenceCommandTest.php` — ReferenceCommand behavior:
  - Generates reference screenshots
  - Uses correct profile and device
  - Integrates with invrt-reference.sh

- `tests/E2E/TestCommandTest.php` — TestCommand behavior:
  - Runs visual regression tests
  - Integrates with invrt-test.sh
  - Compares current vs reference

- `tests/E2E/ConfigCommandTest.php` — ConfigCommand behavior:
  - Displays project configuration
  - Shows merged profile/environment settings
  - Outputs in expected format

---

### Phase 2: Error Handling & Edge Cases (~15 tests)

Create `tests/Unit/ErrorHandlingTest.php` covering edge cases and error scenarios.

**Test Categories:**
- **Configuration Errors:**
  - Missing config.yaml file
  - Invalid YAML syntax
  - Incomplete YAML structure
  
- **Missing Required Values:**
  - Missing project.url
  - Missing profile name when specified
  - Missing environment name when specified
  - Missing settings.max_crawl_depth

- **Credential Errors:**
  - Invalid/missing credentials
  - Malformed auth section
  - Empty username/password

- **Filesystem Errors:**
  - Permission denied on config file
  - Config file in non-existent directory
  - Temp directory creation failures

- **Argument Validation:**
  - Invalid profile name
  - Invalid device name
  - Invalid environment name
  - Unknown command flags

---

### Phase 3: Service Layer Tests (~10 tests)

Create `tests/Unit/EnvironmentServiceTest.php` testing EnvironmentService in isolation.

**Test Categories:**
- **Profile Resolution:**
  - Default profile selection when not specified
  - Custom profile loading
  - Profile override behavior (profile > base settings)

- **Environment Merging:**
  - Environment variables merge correctly
  - Environment settings override profile settings
  - Priority order: base → profile → environment

- **Credential Loading:**
  - Credentials loaded from auth section
  - Username/password extraction
  - Missing credential handling

- **Path Construction:**
  - Proper path joining across platforms
  - URL construction from base + profile/env
  - File path resolution

- **Integration:**
  - EnvironmentService with real fixture config files
  - Multiple profiles and environments in same config
  - Complex nested configuration

---

### Phase 4: Improved Test Organization

**Fixture Management:**
- Create `tests/Fixtures/TestProjectFixture.php` — reusable fixture helper:
  - Temp directory creation/cleanup
  - Config file generation from templates
  - Fixture file paths (config.yaml, cookies.json, etc.)
  - Shared across all test phases

**Test Helpers:**
- Command execution wrapper (capture output, exit code)
- Config file creation helpers
- Environment variable assertion helpers

**Configuration:**
- Update `phpunit.xml` to register `tests/E2E/` directory
- Configure code coverage to include new test directories
- Set proper test suite organization

---

## Files to Create

### E2E Tests
- `tests/E2E/CommandTestCase.php` — base class for E2E tests
- `tests/E2E/InitCommandTest.php` — test InitCommand
- `tests/E2E/CrawlCommandTest.php` — test CrawlCommand with real bash
- `tests/E2E/ReferenceCommandTest.php` — test ReferenceCommand
- `tests/E2E/TestCommandTest.php` — test TestCommand
- `tests/E2E/ConfigCommandTest.php` — test ConfigCommand

### Unit Tests
- `tests/Unit/ErrorHandlingTest.php` — error scenarios
- `tests/Unit/EnvironmentServiceTest.php` — service isolation tests

### Helpers
- `tests/Fixtures/TestProjectFixture.php` — fixture management helper

### Configuration Updates
- `phpunit.xml` — register E2E directory, update coverage paths

---

## Key Decisions

✅ **Real bash script execution** — Not mocked. Tests actual bash scripts to verify real-world behavior.

✅ **PHP/CLI focused** — No Docker tests at this time (deferred for future phase).

✅ **Fixture-driven** — Use temporary directories with realistic config files to test actual workflows.

✅ **Progressive coverage** — Phase 1 builds command test foundation, Phase 2-3 fill gaps and add service tests.

---

## Expected Outcomes

- **Coverage:** Increase from 52 to ~100 tests
- **Command Coverage:** 0% → 100% for all 5 CLI commands
- **Error Handling:** Comprehensive edge case coverage
- **Confidence:** E2E tests verify actual bash workflows

---

## Implementation Order

1. Create base fixtures helper (`tests/Fixtures/TestProjectFixture.php`)
2. Create E2E base class (`tests/E2E/CommandTestCase.php`)
3. Implement E2E command tests (Phase 1) - starts with simplest command
4. Implement error handling tests (Phase 2)
5. Implement service layer tests (Phase 3)
6. Update phpunit.xml configuration
7. Run full test suite and verify coverage

---

## Notes for Refinement

- Consider which bash scripts are testable without actual infrastructure (Playwright, wget, etc.)
- May need stub/mock bash scripts for some E2E tests if dependencies are unavailable
- Error handling tests can be pure unit tests (no bash execution)
- Service layer tests should be isolated from bash scripts
