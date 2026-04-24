#!/usr/bin/env bats

load test_helper.bash

setup() {
  setup_invrt_test
}

teardown() {
  teardown_invrt_test
}

@test "check: writes check yaml with site metadata" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"

  run_invrt check

  [ "$status" -eq 0 ]
  assert_output_contains "Site check complete"
  assert_output_contains "Home"
  assert_file_exists "$TEST_DIR/.invrt/check.yaml"
  assert_yaml_equals "$TEST_DIR/.invrt/check.yaml" "title" "Home"
  assert_yaml_equals "$TEST_DIR/.invrt/check.yaml" "https" "false"
  assert_file_not_contains "$TEST_DIR/.invrt/check.yaml" "redirected_from:"
  assert_file_exists "$TEST_DIR/.invrt/plan.yaml"
  assert_yaml_equals "$TEST_DIR/.invrt/plan.yaml" "project.url" "$SERVER_URL"
  assert_yaml_equals "$TEST_DIR/.invrt/plan.yaml" "project.title" "Home"
  assert_yaml_equals "$TEST_DIR/.invrt/plan.yaml" "pages./.title" "Home"
}

@test "check: preserves user-defined plan keys while updating metadata" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"

  cat > "$TEST_DIR/.invrt/plan.yaml" <<'EOF'
project:
  custom_key: keep-me
pages:
  /:
    onready: scripts/home.onready.js
EOF

  run_invrt check

  [ "$status" -eq 0 ]
  assert_yaml_equals "$TEST_DIR/.invrt/plan.yaml" "project.custom_key" "keep-me"
  assert_yaml_equals "$TEST_DIR/.invrt/plan.yaml" "project.title" "Home"
  assert_yaml_equals "$TEST_DIR/.invrt/plan.yaml" "pages./.onready" "scripts/home.onready.js"
  assert_yaml_equals "$TEST_DIR/.invrt/plan.yaml" "pages./.title" "Home"
}

@test "check: fails when the site is unreachable" {
  seed_basic_config "http://127.0.0.1:19999"

  run_invrt check

  [ "$status" -ne 0 ]
  assert_output_contains "Failed to connect"
}

@test "crawl: fails without config when no url is available" {
  run_invrt_with_stdin $'\n' crawl

  [ "$status" -ne 0 ]
  assert_output_contains "A URL is required to initialize inVRT."
}

@test "crawl: crawls the fixture site and writes discovered urls" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"

  run_invrt crawl -vvv

  [ "$status" -eq 0 ]
  assert_output_contains "Crawling completed"
  assert_file_exists "$TEST_DIR/.invrt/data/anonymous/crawled-paths.text"
  assert_file_exists "$TEST_DIR/.invrt/data/anonymous/logs/crawl.log"
  assert_file_contains "$TEST_DIR/.invrt/data/anonymous/crawled-paths.text" "/about.html"
  assert_file_contains "$TEST_DIR/.invrt/data/anonymous/crawled-paths.text" "/services.html"
  assert_file_contains "$TEST_DIR/.invrt/data/anonymous/crawled-paths.text" "/contact.html"
  assert_file_contains "$TEST_DIR/.invrt/data/anonymous/crawled-paths.text" "/blog.html"
}

@test "crawl: scenario labels in backstop config are short lowercase ids" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"

  run_invrt crawl
  [ "$status" -eq 0 ]

  run_invrt configure-backstop
  [ "$status" -eq 0 ]

  local config_file="$TEST_DIR/.invrt/scripts/backstop.json"
  assert_file_exists "$config_file"
  assert_output_contains "Generated backstop config"
  # All scenario labels should be lowercase letters only (encodeId output).
  run bash -c "jq -r '.scenarios[].label' '$config_file' | grep -qvE '^[a-z]+$'"
  [ "$status" -ne 0 ]
}

@test "crawl: auto initializes from an interactive prompt when config is missing" {
  start_fixture_server

  run_invrt_in_tty_with_stdin "$SERVER_URL"$'\n' crawl

  [ "$status" -eq 0 ]
  assert_output_contains "No configuration file found. Initializing inVRT first."
  assert_output_contains "What URL should inVRT use?"
  assert_file_exists "$TEST_DIR/.invrt/data/anonymous/crawled-paths.text"
  assert_yaml_equals "$TEST_DIR/.invrt/config.yaml" "environments.local.url" "$SERVER_URL"
}

@test "crawl: fails with no usable urls and preserves an empty crawl file" {
  seed_basic_config "http://127.0.0.1:1"

  run_invrt crawl

  [ "$status" -ne 0 ]
  assert_output_contains "No usable URLs were found during crawl."
  assert_output_contains "Last 5 lines of crawl log:"
  assert_file_exists "$TEST_DIR/.invrt/data/anonymous/crawled-paths.text"
  [ ! -s "$TEST_DIR/.invrt/data/anonymous/crawled-paths.text" ]
}

@test "reference: fails without config when no url is available" {
  run_invrt_with_stdin $'\n' reference

  [ "$status" -ne 0 ]
  assert_output_contains "A URL is required to initialize inVRT."
}

@test "reference: captures screenshots from the crawled urls file" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"
  seed_crawled_urls anonymous / /about.html

  run_invrt reference

  [ "$status" -eq 0 ]
  assert_dir_exists "$TEST_DIR/.invrt/data/anonymous/reference"
  assert_png_count_at_least "$TEST_DIR/.invrt/data/anonymous/reference" 2
  assert_file_exists "$TEST_DIR/.invrt/data/anonymous/logs/reference.log"
}

@test "reference: auto triggers crawl when crawled urls are missing" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"

  run_invrt reference

  [ "$status" -eq 0 ]
  assert_output_contains "No crawled URLs found"
  assert_file_exists "$TEST_DIR/.invrt/data/anonymous/crawled-paths.text"
  assert_png_count_at_least "$TEST_DIR/.invrt/data/anonymous/reference" 1
}

@test "reference: shows debug output at vvv" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"
  seed_crawled_urls anonymous /

  run_invrt -vvv reference

  [ "$status" -eq 0 ]
  assert_output_contains "[debug] Bootstrapping command"
  assert_output_contains "[debug] Running Playwright command"
  assert_output_contains "[debug] Playwright exit code: 0"
}

@test "reference: fails when the crawl file is empty" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"
  seed_crawled_urls anonymous

  run_invrt reference

  [ "$status" -ne 0 ]
  assert_output_contains "No crawled URLs are available. Crawl has run but found no usable URLs."
}

@test "test: fails without config when no url is available" {
  run_invrt_with_stdin $'\n' test

  [ "$status" -ne 0 ]
  assert_output_contains "A URL is required to initialize inVRT."
}

@test "test: captures references on first run and skips that step on the second run" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"
  seed_crawled_urls anonymous / /about.html

  run_invrt test

  [ "$status" -eq 0 ]
  assert_output_contains "No reference screenshots found"
  assert_dir_exists "$TEST_DIR/.invrt/data/anonymous/reference"
  assert_dir_exists "$TEST_DIR/.invrt/data/anonymous/results"
  assert_file_exists "$TEST_DIR/.invrt/data/anonymous/logs/reference.log"
  assert_file_exists "$TEST_DIR/.invrt/data/anonymous/logs/test.log"

  run_invrt test

  [ "$status" -eq 0 ]
  assert_output_not_contains "No reference screenshots found"
  assert_output_contains "Testing"
}

@test "test: auto triggers reference and crawl on first run" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"

  run_invrt test

  [ "$status" -eq 0 ]
  assert_output_contains "No reference screenshots found"
  assert_output_contains "No crawled URLs found"
  assert_png_count_at_least "$TEST_DIR/.invrt/data/anonymous/reference" 1
  assert_dir_exists "$TEST_DIR/.invrt/data/anonymous/results"
}

@test "test: handles very long crawled url paths" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"

  local long_query long_slug
  long_query="$(printf 'a%.0s' $(seq 1 500))"
  long_slug="$(printf 'segment%.0s' $(seq 1 45))"
  seed_crawled_urls anonymous "/about.html?long=$long_query&slug=$long_slug"

  run_invrt test

  [ "$status" -eq 0 ]
  assert_dir_exists "$TEST_DIR/.invrt/data/anonymous/results"
}

@test "approve: succeeds after a test run" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"

  run_invrt test
  [ "$status" -eq 0 ]

  run_invrt approve

  [ "$status" -eq 0 ]
  assert_output_contains "Approving latest results"
}

@test "generate-playwright: generates spec from crawled urls" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"
  seed_crawled_urls anonymous / /about.html

  run_invrt generate-playwright

  [ "$status" -eq 0 ]
  assert_output_contains "Generated playwright spec"
  assert_file_exists "$TEST_DIR/.invrt/data/anonymous/desktop.spec.ts"
  assert_file_exists "$TEST_DIR/.invrt/data/anonymous/playwright.config.ts"
  assert_file_contains "$TEST_DIR/.invrt/data/anonymous/desktop.spec.ts" "import { test"
  assert_file_contains "$TEST_DIR/.invrt/data/anonymous/desktop.spec.ts" "networkidle"
}

@test "baseline: runs full pipeline and produces approved screenshots" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"

  run_invrt baseline

  [ "$status" -eq 0 ]
  assert_output_contains "Site check complete"
  assert_output_contains "Crawling completed"
  assert_output_contains "Generated playwright spec"
  assert_output_contains "Approving latest results"
  assert_dir_exists "$TEST_DIR/.invrt/data/anonymous/reference"
  assert_dir_exists "$TEST_DIR/.invrt/data/anonymous/results"
  assert_file_exists "$TEST_DIR/.invrt/data/anonymous/logs/reference.log"
  assert_file_exists "$TEST_DIR/.invrt/data/anonymous/logs/test.log"
}

@test "init: automatically runs baseline after init" {
  start_fixture_server

  run_invrt_in_tty_with_stdin "$SERVER_URL"$'\n' init

  [ "$status" -eq 0 ]
  assert_output_contains "InVRT successfully initialized!"
  assert_output_contains "Running baseline"
  assert_dir_exists "$TEST_DIR/.invrt/data/anonymous/reference"
  assert_dir_exists "$TEST_DIR/.invrt/data/anonymous/results"
}

@test "init: skips baseline with --skip-baseline" {
  start_fixture_server

  run_invrt_in_tty_with_stdin "$SERVER_URL"$'\n' init --skip-baseline

  [ "$status" -eq 0 ]
  assert_output_contains "InVRT successfully initialized!"
  assert_dir_not_exists "$TEST_DIR/.invrt/data/anonymous/bitmaps/reference/desktop"
}
