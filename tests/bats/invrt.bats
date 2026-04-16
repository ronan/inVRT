#!/usr/bin/env bats
#
# BATS end-to-end tests for the inVRT CLI.
#
# Each test runs the real `bin/invrt` binary via a subprocess in an isolated
# temp directory, verifying exit codes and key output strings.
#
# Docs: https://bats-core.readthedocs.io/en/stable/

INVRT_ROOT="$(cd "$(dirname "$BATS_TEST_FILENAME")/../.." && pwd)"
INVRT_BIN="$INVRT_ROOT/bin/invrt"

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

@test "version: shows version string matching package.json" {
    expected="$(node -p "require('$INVRT_ROOT/package.json').version")"
    run "$INVRT_BIN" --version
    [ "$status" -eq 0 ]
    [[ "$output" == *"$expected"* ]]
}

@test "help: lists all core commands" {
    run "$INVRT_BIN" list
    [ "$status" -eq 0 ]
    [[ "$output" == *"init"* ]]
    [[ "$output" == *"approve"* ]]
    [[ "$output" == *"baseline"* ]]
    [[ "$output" == *"crawl"* ]]
    [[ "$output" == *"reference"* ]]
    [[ "$output" == *"test"* ]]
    [[ "$output" == *"config"* ]]
}

# ---------------------------------------------------------------------------
# init
# ---------------------------------------------------------------------------

@test "init: creates .invrt directory structure" {
    run "$INVRT_BIN" init https://example.test
    [ "$status" -eq 0 ]
    [ -d "$TEST_DIR/.invrt" ]
    [ -f "$TEST_DIR/.invrt/config.yaml" ]
    [ -d "$TEST_DIR/.invrt/data" ]
    [ -d "$TEST_DIR/.invrt/scripts" ]
    [ -f "$TEST_DIR/.invrt/exclude_urls.txt" ]
}

@test "init: config.yaml contains the selected url and core sections" {
    "$INVRT_BIN" init https://example.test --environment=stage > /dev/null 2>&1
    run grep -l "environments:" "$TEST_DIR/.invrt/config.yaml"
    [ "$status" -eq 0 ]
    run grep "https://example.test" "$TEST_DIR/.invrt/config.yaml"
    [ "$status" -eq 0 ]
    run grep "profiles:" "$TEST_DIR/.invrt/config.yaml"
    [ "$status" -eq 0 ]
    run grep "devices:" "$TEST_DIR/.invrt/config.yaml"
    [ "$status" -eq 0 ]
}

@test "init: outputs success message" {
    run "$INVRT_BIN" init https://example.test
    [ "$status" -eq 0 ]
    [[ "$output" == *"Initializing InVRT"* ]]
    [[ "$output" == *"initialized"* ]]
}

@test "init: fails when already initialized" {
    "$INVRT_BIN" init https://example.test > /dev/null 2>&1
    run "$INVRT_BIN" init https://example.test
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
    "$INVRT_BIN" init https://example.test > /dev/null 2>&1
    run "$INVRT_BIN" config
    [ "$status" -eq 0 ]
    [[ "$output" == *"INVRT_URL"* ]]
    [[ "$output" == *"INVRT_DIRECTORY"* ]]
    [[ "$output" == *"INVRT_PROFILE"* ]]
}

@test "config: reflects custom URL from config file" {
    "$INVRT_BIN" init https://example.test > /dev/null 2>&1
    cat > "$TEST_DIR/.invrt/config.yaml" << 'EOF'
name: Test Project
environments:
  local:
    url: http://localhost:9999
profiles:
  anonymous: {}
devices:
  desktop:
    viewport_width: 1024
    viewport_height: 768
settings: {}
EOF
    run "$INVRT_BIN" config
    [ "$status" -eq 0 ]
    [[ "$output" == *"localhost:9999"* ]]
}

@test "config: fails on invalid YAML" {
    "$INVRT_BIN" init https://example.test > /dev/null 2>&1
    echo ": invalid: yaml: [[[" > "$TEST_DIR/.invrt/config.yaml"
    run "$INVRT_BIN" config
    [ "$status" -ne 0 ]
    [[ "$output" == *"Error reading config file"* ]]
}

# ---------------------------------------------------------------------------
# crawl / reference / test — auto init needs a url
# ---------------------------------------------------------------------------

@test "crawl: fails without config file when no url is available" {
    run bash -lc "printf '\n' | \"$INVRT_BIN\" crawl"
    [ "$status" -ne 0 ]
    [[ "$output" == *"A URL is required to initialize inVRT."* ]]
}

@test "reference: fails without config file when no url is available" {
    run bash -lc "printf '\n' | \"$INVRT_BIN\" reference"
    [ "$status" -ne 0 ]
    [[ "$output" == *"A URL is required to initialize inVRT."* ]]
}

@test "test: fails without config file when no url is available" {
    run bash -lc "printf '\n' | \"$INVRT_BIN\" test"
    [ "$status" -ne 0 ]
    [[ "$output" == *"A URL is required to initialize inVRT."* ]]
}

@test "crawl: auto initializes when INVRT_URL is provided" {
    export INVRT_URL="https://example.test"
    run "$INVRT_BIN" crawl
    [ "$status" -ne 0 ]
    [[ "$output" == *"Initializing InVRT"* ]]
}
