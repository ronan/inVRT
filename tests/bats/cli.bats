#!/usr/bin/env bats

load test_helper.bash

setup() {
  setup_invrt_test
}

teardown() {
  teardown_invrt_test
}

@test "version: shows version string matching package.json" {
  local expected
  expected="$(node -p "require('$APP_ROOT/package.json').version")"

  run_invrt --version

  [ "$status" -eq 0 ]
  assert_output_contains "$expected"
}

@test "help: lists all core commands" {
  run_invrt list

  [ "$status" -eq 0 ]
  assert_output_contains "init"
  assert_output_contains "approve"
  assert_output_contains "baseline"
  assert_output_contains "check"
  assert_output_contains "crawl"
  assert_output_contains "reference"
  assert_output_contains "test"
  assert_output_contains "config"
  assert_output_contains "info"
}

@test "init: writes selected url environment profile and device" {
  run_invrt init https://example.test --environment=stage --profile=editor --device=tablet --skip-baseline

  [ "$status" -eq 0 ]
  assert_output_not_contains "INVRT_URL must be set"
  assert_dir_exists "$TEST_DIR/.invrt"
  assert_dir_exists "$TEST_DIR/.invrt/data"
  assert_dir_exists "$TEST_DIR/.invrt/scripts"
  assert_file_exists "$TEST_DIR/.invrt/scripts/onready.ts"
  assert_file_exists "$TEST_DIR/.invrt/plan.yaml"
  [ ! -f "$TEST_DIR/.invrt/config.yaml" ]
  assert_file_contains "$TEST_DIR/.invrt/scripts/onready.ts" "Runs after the page is ready"
  assert_yaml_equals "$TEST_DIR/.invrt/plan.yaml" "environments.stage.url" "https://example.test"
  assert_yaml_equals "$TEST_DIR/.invrt/plan.yaml" "profiles.editor" "[]"
  assert_yaml_equals "$TEST_DIR/.invrt/plan.yaml" "devices.tablet" "[]"
  assert_yaml_equals "$TEST_DIR/.invrt/plan.yaml" "project.url" "https://example.test"
  assert_yaml_equals "$TEST_DIR/.invrt/plan.yaml" "pages./" "[]"
  assert_file_contains "$TEST_DIR/.invrt/plan.yaml" "exclude:"
  assert_file_contains "$TEST_DIR/.invrt/plan.yaml" "/logout"

  local project_id
  project_id="$(yaml_get "$TEST_DIR/.invrt/plan.yaml" "project.id")"
  [[ -n "$project_id" ]]
  [[ "$project_id" =~ ^[a-z]+$ ]]
}

@test "init: prompts for url in a tty when the argument is missing" {
  run_invrt_in_tty_with_stdin $'https://prompted.example\n' init --environment=review --profile=editor --device=tablet --skip-baseline

  [ "$status" -eq 0 ]
  assert_output_contains "What URL should inVRT use?"
  assert_yaml_equals "$TEST_DIR/.invrt/plan.yaml" "environments.review.url" "https://prompted.example"
  assert_yaml_equals "$TEST_DIR/.invrt/plan.yaml" "profiles.editor" "[]"
  assert_yaml_equals "$TEST_DIR/.invrt/plan.yaml" "devices.tablet" "[]"
}

@test "init: fails when already initialized" {
  run_invrt init https://example.test --skip-baseline
  [ "$status" -eq 0 ]

  run_invrt init https://example.test --skip-baseline

  [ "$status" -ne 0 ]
  assert_output_contains "already initialized"
}

@test "config: fails without a config file" {
  run_invrt config

  [ "$status" -ne 0 ]
  assert_output_contains "Configuration file not found"
  assert_output_contains "invrt init"
}

@test "config: shows resolved configuration after init" {
  run_invrt init https://example.test --skip-baseline
  [ "$status" -eq 0 ]

  run_invrt config

  [ "$status" -eq 0 ]
  assert_output_contains "INVRT_URL: https://example.test"
  assert_output_contains "INVRT_DIRECTORY:"
  assert_output_contains "INVRT_PROFILE: anonymous"
}

@test "config: fails on invalid yaml" {
  mkdir -p "$TEST_DIR/.invrt"
  printf ': invalid: yaml: [[[\n' > "$TEST_DIR/.invrt/plan.yaml"

  run_invrt config

  [ "$status" -ne 0 ]
  assert_output_contains "Error reading config file"
}

@test "info: shows project summary and crawled page count" {
  mkdir -p "$TEST_DIR/.invrt/data/anonymous"
  cat > "$TEST_DIR/.invrt/plan.yaml" <<'EOF'
project:
  name: My Test Project
  id: sampleprojectid
environments:
  local:
    url: https://local.example.com
  staging:
    url: https://staging.example.com
profiles:
  anonymous: {}
  admin:
    username: admin
    password: secret
devices:
  desktop: {}
  mobile:
    viewport_width: 375
    viewport_height: 667
EOF
  printf "/\n/about\n/contact\n" > "$TEST_DIR/.invrt/data/anonymous/crawled-paths.text"

  run_invrt info

  [ "$status" -eq 0 ]
  assert_output_contains "My Test Project"
  assert_output_contains "Project ID"
  assert_output_contains "sampleprojectid"
  assert_output_contains "local"
  assert_output_contains "staging"
  assert_output_contains "anonymous"
  assert_output_contains "admin"
  assert_output_contains "desktop"
  assert_output_contains "mobile"
  assert_output_contains "Planned pages"
  assert_output_contains "3"
}

@test "config: discovers plan.yaml in invrt/" {
  mkdir -p "$TEST_DIR/invrt"
  cat > "$TEST_DIR/invrt/plan.yaml" <<'EOF'
project:
  url: https://discovered.example
environments:
  local:
    url: https://discovered.example
EOF

  run_invrt config

  [ "$status" -eq 0 ]
  assert_output_contains "INVRT_PLAN_FILE"
  assert_output_contains "invrt/plan.yaml"
  assert_output_contains "INVRT_URL: https://discovered.example"
}

@test "config: discovers plan.yaml in .ddev/.invrt/" {
  mkdir -p "$TEST_DIR/.ddev/.invrt"
  cat > "$TEST_DIR/.ddev/.invrt/plan.yaml" <<'EOF'
project:
  url: https://ddev-dot.example
environments:
  local:
    url: https://ddev-dot.example
EOF

  run_invrt config

  [ "$status" -eq 0 ]
  assert_output_contains ".ddev/.invrt/plan.yaml"
  assert_output_contains "INVRT_URL: https://ddev-dot.example"
}

@test "config: discovers plan.yaml in .ddev/invrt/" {
  mkdir -p "$TEST_DIR/.ddev/invrt"
  cat > "$TEST_DIR/.ddev/invrt/plan.yaml" <<'EOF'
project:
  url: https://ddev.example
environments:
  local:
    url: https://ddev.example
EOF

  run_invrt config

  [ "$status" -eq 0 ]
  assert_output_contains ".ddev/invrt/plan.yaml"
  assert_output_contains "INVRT_URL: https://ddev.example"
}

@test "config: prefers invrt/ over .invrt/" {
  mkdir -p "$TEST_DIR/.invrt" "$TEST_DIR/invrt"
  cat > "$TEST_DIR/invrt/plan.yaml" <<'EOF'
project:
  url: https://primary.example
environments:
  local:
    url: https://primary.example
EOF
  cat > "$TEST_DIR/.invrt/plan.yaml" <<'EOF'
project:
  url: https://secondary.example
environments:
  local:
    url: https://secondary.example
EOF

  run_invrt config

  [ "$status" -eq 0 ]
  assert_output_contains "INVRT_URL: https://primary.example"
}
