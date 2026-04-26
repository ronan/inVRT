#!/usr/bin/env bats

load test_helper.bash

setup() {
  setup_invrt_test
}

teardown() {
  teardown_invrt_test
}

@test "check: enriches plan.yaml with site metadata" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"

  run_invrt check

  [ "$status" -eq 0 ]
  assert_output_contains "Site check complete"
  assert_output_contains "Home"
  assert_file_exists "$TEST_DIR/.invrt/plan.yaml"
  assert_yaml_equals "$TEST_DIR/.invrt/plan.yaml" "project.url" "$SERVER_URL"
  assert_yaml_equals "$TEST_DIR/.invrt/plan.yaml" "project.title" "Home"
  assert_yaml_equals "$TEST_DIR/.invrt/plan.yaml" "pages./.title" "Home"
  assert_file_contains "$TEST_DIR/.invrt/plan.yaml" "checked_at:"
  assert_file_not_contains "$TEST_DIR/.invrt/plan.yaml" "https:"
  assert_file_not_contains "$TEST_DIR/.invrt/plan.yaml" "redirected_from:"
}

@test "check: preserves user-defined plan keys while updating metadata" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"

  cat > "$TEST_DIR/.invrt/plan.yaml" <<EOF
project:
  custom_key: keep-me
environments:
  local:
    url: $SERVER_URL
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
  assert_file_exists "$TEST_DIR/.invrt/logs/crawled-paths-local-anonymous.yaml"
  assert_file_exists "$TEST_DIR/.invrt/logs/crawl-local-anonymous.log"
  assert_file_contains "$TEST_DIR/.invrt/logs/crawled-paths-local-anonymous.yaml" "/about.html: About"
  assert_file_contains "$TEST_DIR/.invrt/logs/crawled-paths-local-anonymous.yaml" "/services.html: Services"
  assert_file_contains "$TEST_DIR/.invrt/logs/crawled-paths-local-anonymous.yaml" "/contact.html: Contact"
  assert_file_contains "$TEST_DIR/.invrt/logs/crawled-paths-local-anonymous.yaml" "/blog.html: Blog"
  assert_file_exists "$TEST_DIR/.invrt/plan.yaml"
  assert_file_contains "$TEST_DIR/.invrt/plan.yaml" "/about.html:"
  assert_file_contains "$TEST_DIR/.invrt/plan.yaml" "profiles:"
  assert_file_contains "$TEST_DIR/.invrt/plan.yaml" "anonymous:"
  assert_file_contains "$TEST_DIR/.invrt/plan.yaml" "id:"
  assert_file_contains "$TEST_DIR/.invrt/plan.yaml" "/blog:"
  assert_file_contains "$TEST_DIR/.invrt/plan.yaml" "/2026:"
  assert_file_contains "$TEST_DIR/.invrt/plan.yaml" "/post.html:"
  assert_yaml_equals "$TEST_DIR/.invrt/plan.yaml" "project.title" "Home"
  assert_yaml_equals "$TEST_DIR/.invrt/plan.yaml" "pages./.title" "Home"
  assert_file_contains "$TEST_DIR/.invrt/plan.yaml" "title: About"
  assert_file_contains "$TEST_DIR/.invrt/plan.yaml" "title: Featured Post"
}

@test "crawl: auto initializes from an interactive prompt when config is missing" {
  start_fixture_server

  run_invrt_in_tty_with_stdin "$SERVER_URL"$'\n' crawl

  [ "$status" -eq 0 ]
  assert_output_contains "No configuration file found. Initializing inVRT first."
  assert_output_contains "What URL should inVRT use?"
  assert_file_exists "$TEST_DIR/.invrt/logs/crawled-paths-local-anonymous.yaml"
  assert_yaml_equals "$TEST_DIR/.invrt/plan.yaml" "environments.local.url" "$SERVER_URL"
}

@test "crawl: fails with no usable urls and preserves an empty crawl file" {
  seed_basic_config "http://127.0.0.1:1"

  run_invrt crawl

  [ "$status" -ne 0 ]
  assert_output_contains "No usable URLs were found during crawl."
  assert_output_contains "Last 5 lines of crawl log:"
  assert_file_exists "$TEST_DIR/.invrt/logs/crawled-paths-local-anonymous.yaml"
  [ ! -s "$TEST_DIR/.invrt/logs/crawled-paths-local-anonymous.yaml" ]
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
  assert_dir_exists "$TEST_DIR/.invrt/reference"
  assert_png_count_at_least "$TEST_DIR/.invrt/reference" 2
  assert_file_exists "$TEST_DIR/.invrt/logs/reference-local-anonymous.log"
}

@test "reference: auto triggers crawl when crawled urls are missing" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"

  run_invrt reference

  [ "$status" -eq 0 ]
  assert_output_contains "No planned pages found"
  assert_file_exists "$TEST_DIR/.invrt/logs/crawled-paths-local-anonymous.yaml"
  assert_png_count_at_least "$TEST_DIR/.invrt/reference" 1
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
  assert_dir_exists "$TEST_DIR/.invrt/reference"
  assert_dir_exists "$TEST_DIR/.invrt/results"
  assert_file_exists "$TEST_DIR/.invrt/logs/reference-local-anonymous.log"
  assert_file_exists "$TEST_DIR/.invrt/logs/test-local-anonymous.log"

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
  assert_output_contains "No planned pages found"
  assert_png_count_at_least "$TEST_DIR/.invrt/reference" 1
  assert_dir_exists "$TEST_DIR/.invrt/results"
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
  assert_dir_exists "$TEST_DIR/.invrt/results"
}

@test "test: regenerates playwright spec from plan.yaml when references already exist" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"

  cat > "$TEST_DIR/.invrt/plan.yaml" <<EOF
project: {}
environments:
  local:
    url: $SERVER_URL
pages:
  /about.html: {}
  /contact.html: {}
EOF

  run_invrt reference
  [ "$status" -eq 0 ]
  assert_file_exists "$TEST_DIR/.invrt/local.spec.ts"
  assert_file_contains "$TEST_DIR/.invrt/local.spec.ts" "/about.html"

  cat > "$TEST_DIR/.invrt/plan.yaml" <<EOF
project: {}
environments:
  local:
    url: $SERVER_URL
pages:
  /contact.html: {}
EOF

  run_invrt test

  [ "$status" -eq 0 ]
  assert_file_contains "$TEST_DIR/.invrt/local.spec.ts" "/contact.html"
  assert_file_not_contains "$TEST_DIR/.invrt/local.spec.ts" "/about.html"
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

@test "generate-playwright: generates spec from plan pages" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"

  mkdir -p "$TEST_DIR/.invrt/scripts"
  cat > "$TEST_DIR/.invrt/scripts/root-setup.ts" <<'EOF'
console.log('root setup hook');
EOF
  cat > "$TEST_DIR/.invrt/scripts/child-ready.js" <<'EOF'
console.log('child ready hook');
EOF
  cat > "$TEST_DIR/.invrt/plan.yaml" <<EOF
project: {}
environments:
  local:
    url: $SERVER_URL
pages:
  /:
    setup: root-setup.ts
    onready: |
      console.log('root ready hook');
    teardown: |
      console.log('shared teardown hook');
    /about.html:
      onready: child-ready.js
EOF

  run_invrt generate-playwright

  [ "$status" -eq 0 ]
  assert_output_contains "Generated playwright spec"
  assert_file_exists "$TEST_DIR/.invrt/local.spec.ts"
  assert_file_exists "$TEST_DIR/.invrt/playwright.config.ts"
  assert_file_contains "$TEST_DIR/.invrt/local.spec.ts" "import { test"
  assert_file_contains "$TEST_DIR/.invrt/local.spec.ts" "networkidle"
  assert_file_contains "$TEST_DIR/.invrt/local.spec.ts" "try {"
  assert_file_contains "$TEST_DIR/.invrt/local.spec.ts" "finally"
  run bash -c "grep -c \"root setup hook\" '$TEST_DIR/.invrt/local.spec.ts'"
  [ "$status" -eq 0 ]
  [ "$output" -eq 2 ]
  run bash -c "grep -c \"shared teardown hook\" '$TEST_DIR/.invrt/local.spec.ts'"
  [ "$status" -eq 0 ]
  [ "$output" -eq 2 ]
  run bash -c "grep -c \"root ready hook\" '$TEST_DIR/.invrt/local.spec.ts'"
  [ "$status" -eq 0 ]
  [ "$output" -eq 1 ]
  run bash -c "grep -c \"child ready hook\" '$TEST_DIR/.invrt/local.spec.ts'"
  [ "$status" -eq 0 ]
  [ "$output" -eq 1 ]
}

@test "generate-playwright: uses page titles as test names" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"

  cat > "$TEST_DIR/.invrt/plan.yaml" <<EOF
project:
  title: My Site
environments:
  local:
    url: $SERVER_URL
pages:
  /:
    title: My Site
  /about.html:
    title: About Us
EOF

  run_invrt generate-playwright

  [ "$status" -eq 0 ]
  run bash -c "find '$TEST_DIR/.invrt' -name '*.spec.ts' -print -quit"
  [ -n "$output" ]
  local spec_file="$output"
  assert_file_contains "$spec_file" 'test("My Site"'
  assert_file_contains "$spec_file" 'test("About Us"'
}

@test "generate-playwright: fails when a referenced script file is missing" {
  start_fixture_server
  seed_basic_config "$SERVER_URL"

  cat > "$TEST_DIR/.invrt/plan.yaml" <<EOF
project: {}
environments:
  local:
    url: $SERVER_URL
pages:
  /:
    setup: missing.ts
EOF

  run_invrt generate-playwright

  [ "$status" -ne 0 ]
  assert_output_contains "Script file not found: missing.ts"
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
  assert_dir_exists "$TEST_DIR/.invrt/reference"
  assert_dir_exists "$TEST_DIR/.invrt/results"
  assert_file_exists "$TEST_DIR/.invrt/logs/reference-local-anonymous.log"
  assert_file_exists "$TEST_DIR/.invrt/logs/test-local-anonymous.log"
}

@test "init: automatically runs baseline after init" {
  start_fixture_server

  run_invrt_in_tty_with_stdin "$SERVER_URL"$'\n' init

  [ "$status" -eq 0 ]
  assert_output_contains "InVRT successfully initialized!"
  assert_output_contains "Running baseline"
  assert_dir_exists "$TEST_DIR/.invrt/reference"
  assert_dir_exists "$TEST_DIR/.invrt/results"
}

@test "init: skips baseline with --skip-baseline" {
  start_fixture_server

  run_invrt_in_tty_with_stdin "$SERVER_URL"$'\n' init --skip-baseline

  [ "$status" -eq 0 ]
  assert_output_contains "InVRT successfully initialized!"
}
