#!/usr/bin/env bats
#
# BATS end-to-end tests for the inVRT CLI.
#
# Each test runs the real `bin/invrt` binary via a subprocess in an isolated
# temp directory, verifying exit codes and key output strings.
#
# Docs: https://bats-core.readthedocs.io/en/stable/

INVRT_BIN="$(cd "$(dirname "$BATS_TEST_FILENAME")/../.." && pwd)/bin/invrt"

setup() {
    TEST_DIR="$(mktemp -d)"
    export INVRT_CWD="$TEST_DIR"
}

teardown() {
    rm -rf "$TEST_DIR"
}

# ---------------------------------------------------------------------------
# version / help
# ---------------------------------------------------------------------------

@test "version: shows version string" {
    run "$INVRT_BIN" --version
    [ "$status" -eq 0 ]
    [[ "$output" == *"inVRT"* ]]
}

@test "help: lists all core commands" {
    run "$INVRT_BIN" list
    [ "$status" -eq 0 ]
    [[ "$output" == *"init"* ]]
    [[ "$output" == *"crawl"* ]]
    [[ "$output" == *"reference"* ]]
    [[ "$output" == *"test"* ]]
    [[ "$output" == *"config"* ]]
}

# ---------------------------------------------------------------------------
# init
# ---------------------------------------------------------------------------

@test "init: creates .invrt directory structure" {
    run "$INVRT_BIN" init
    [ "$status" -eq 0 ]
    [ -d "$TEST_DIR/.invrt" ]
    [ -f "$TEST_DIR/.invrt/config.yaml" ]
    [ -d "$TEST_DIR/.invrt/data" ]
    [ -d "$TEST_DIR/.invrt/scripts" ]
    [ -f "$TEST_DIR/.invrt/exclude_urls.txt" ]
}

@test "init: outputs success message" {
    run "$INVRT_BIN" init
    [ "$status" -eq 0 ]
    [[ "$output" == *"Initializing InVRT"* ]]
    [[ "$output" == *"initialized"* ]]
}

@test "init: fails when already initialized" {
    "$INVRT_BIN" init > /dev/null 2>&1
    run "$INVRT_BIN" init
    [ "$status" -ne 0 ]
    [[ "$output" == *"already initialized"* ]]
}

# ---------------------------------------------------------------------------
# config
# ---------------------------------------------------------------------------

@test "config: fails without config file" {
    run "$INVRT_BIN" config
    [ "$status" -ne 0 ]
    [[ "$output" == *"Configuration file not found"* ]]
    [[ "$output" == *"invrt init"* ]]
}

@test "config: shows resolved configuration after init" {
    "$INVRT_BIN" init > /dev/null 2>&1
    run "$INVRT_BIN" config
    [ "$status" -eq 0 ]
    [[ "$output" == *"INVRT_URL"* ]]
    [[ "$output" == *"INVRT_DIRECTORY"* ]]
    [[ "$output" == *"INVRT_PROFILE"* ]]
}

# ---------------------------------------------------------------------------
# crawl
# ---------------------------------------------------------------------------

@test "crawl: fails without config file" {
    run "$INVRT_BIN" crawl
    [ "$status" -ne 0 ]
    [[ "$output" == *"Configuration file not found"* ]]
}
