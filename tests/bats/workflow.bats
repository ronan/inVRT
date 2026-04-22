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
  assert_file_exists "$TEST_DIR/.invrt/data/local/check.yaml"
  assert_yaml_equals "$TEST_DIR/.invrt/data/local/check.yaml" "title" "Home"
  assert_yaml_equals "$TEST_DIR/.invrt/data/local/check.yaml" "https" "false"
  assert_file_not_contains "$TEST_DIR/.invrt/data/local/check.yaml" "redirected_from:"
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
  assert_file_exists "$TEST_DIR/.invrt/data/local/anonymous/crawled_urls.txt"
  assert_file_exists "$TEST_DIR/.invrt/data/local/anonymous/logs/crawl.log"
  assert_file_contains "$TEST_DIR/.invrt/data/local/anonymous/crawled_urls.txt" "/about.html"
  assert_file_contains "$TEST_DIR/.invrt/data/local/anonymous/crawled_urls.txt" "/services.html"
  assert_file_contains "$TEST_DIR/.invrt/data/local/anonymous/crawled_urls.txt" "/contact.html"
  assert_file_contains "$TEST_DIR/.invrt/data/local/anonymous/crawled_urls.txt" "/blog.html"
  assert_file_exists "$TEST_DIR/.invrt/data/local/anonymous/desktop/backstop.json"
  assert_output_contains "Generated backstop config"
}

@test "crawl: scenario labels in backstop config are short lowercase ids" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"

  run_invrt crawl

  [ "$status" -eq 0 ]
  local config_file="$TEST_DIR/.invrt/data/local/anonymous/desktop/backstop.json"
  assert_file_exists "$config_file"
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
  assert_file_exists "$TEST_DIR/.invrt/data/local/anonymous/crawled_urls.txt"
  assert_yaml_equals "$TEST_DIR/.invrt/config.yaml" "environments.local.url" "$SERVER_URL"
}

@test "crawl: fails with no usable urls and preserves an empty crawl file" {
  seed_basic_config "http://127.0.0.1:1"

  run_invrt crawl

  [ "$status" -ne 0 ]
  assert_output_contains "No usable URLs were found during crawl."
  assert_output_contains "Last 5 lines of crawl log:"
  assert_file_exists "$TEST_DIR/.invrt/data/local/anonymous/crawled_urls.txt"
  [ ! -s "$TEST_DIR/.invrt/data/local/anonymous/crawled_urls.txt" ]
}

@test "reference: fails without config when no url is available" {
  run_invrt_with_stdin $'\n' reference

  [ "$status" -ne 0 ]
  assert_output_contains "A URL is required to initialize inVRT."
}

@test "reference: captures screenshots from the crawled urls file" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"
  seed_crawled_urls local anonymous / /about.html

  run_invrt reference

  [ "$status" -eq 0 ]
  assert_dir_exists "$TEST_DIR/.invrt/data/local/anonymous/desktop/bitmaps/reference"
  assert_png_count_at_least "$TEST_DIR/.invrt/data/local/anonymous/desktop/bitmaps/reference" 2
  assert_file_exists "$TEST_DIR/.invrt/data/local/anonymous/desktop/reference_results.txt"
}

@test "reference: auto triggers crawl when crawled urls are missing" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"

  run_invrt reference

  [ "$status" -eq 0 ]
  assert_output_contains "No crawled URLs found"
  assert_file_exists "$TEST_DIR/.invrt/data/local/anonymous/crawled_urls.txt"
  assert_png_count_at_least "$TEST_DIR/.invrt/data/local/anonymous/desktop/bitmaps/reference" 1
}

@test "reference: shows debug output at vvv" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"
  seed_crawled_urls local anonymous /

  run_invrt -vvv reference

  [ "$status" -eq 0 ]
  assert_output_contains "[debug] Bootstrapping command"
  assert_output_contains "[debug] Running BackstopJS command"
  assert_output_contains "[debug] BackstopJS exit code: 0"
}

@test "reference: fails when the crawl file is empty" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"
  seed_crawled_urls local anonymous

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
  seed_crawled_urls local anonymous / /about.html

  run_invrt test

  [ "$status" -eq 0 ]
  assert_output_contains "No reference screenshots found"
  assert_dir_exists "$TEST_DIR/.invrt/data/local/anonymous/desktop/bitmaps/reference"
  assert_dir_exists "$TEST_DIR/.invrt/data/local/anonymous/desktop/bitmaps/test"
  assert_file_exists "$TEST_DIR/.invrt/data/local/anonymous/desktop/reference_results.txt"
  assert_file_exists "$TEST_DIR/.invrt/data/local/anonymous/desktop/test_results.txt"

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
  assert_png_count_at_least "$TEST_DIR/.invrt/data/local/anonymous/desktop/bitmaps/reference" 1
  assert_png_count_at_least "$TEST_DIR/.invrt/data/local/anonymous/desktop/bitmaps/test" 1
}

@test "test: handles very long crawled url paths" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"

  local long_query long_slug
  long_query="$(printf 'a%.0s' $(seq 1 500))"
  long_slug="$(printf 'segment%.0s' $(seq 1 45))"
  seed_crawled_urls local anonymous "/about.html?long=$long_query&slug=$long_slug"

  run_invrt test

  [ "$status" -eq 0 ]
  assert_png_count_at_least "$TEST_DIR/.invrt/data/local/anonymous/desktop/bitmaps/test" 1
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

@test "baseline: builds missing artifacts and approves them" {
  start_fixture_server

  run_invrt_in_tty_with_stdin "$SERVER_URL"$'\n' baseline

  [ "$status" -eq 0 ]
  assert_output_contains "No configuration file found. Initializing inVRT first."
  assert_output_contains "No reference screenshots found"
  assert_output_contains "No test screenshots found"
  assert_output_contains "Approving latest results"
  assert_dir_exists "$TEST_DIR/.invrt/data/local/anonymous/desktop/bitmaps/reference"
  assert_dir_exists "$TEST_DIR/.invrt/data/local/anonymous/desktop/bitmaps/test"
}
