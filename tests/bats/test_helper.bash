#!/usr/bin/env bash

APP_ROOT="$(cd "$(dirname "$BATS_TEST_FILENAME")/../.." && pwd)"
APP_BIN="$APP_ROOT/bin/invrt"
FALLBACK_TEST_ROOT="$APP_ROOT/scratch/tests"

slugify() {
  printf '%s' "$1" \
    | tr '[:upper:]' '[:lower:]' \
    | sed -E 's/[^a-z0-9]+/-/g; s/^-+//; s/-+$//'
}

choose_test_root() {
  if [ -n "${INVRT_TEST_ROOT:-}" ]; then
    printf '%s' "$INVRT_TEST_ROOT"
    return
  fi

  if mkdir -p /scratch/tests 2>/dev/null; then
    printf '%s' /scratch/tests
    return
  fi

  mkdir -p "$FALLBACK_TEST_ROOT"
  printf '%s' "$FALLBACK_TEST_ROOT"
}

clear_invrt_runtime_env() {
  local name

  while IFS='=' read -r name _; do
    case "$name" in
      INVRT_CWD|INVRT_DIRECTORY|INVRT_CONFIG_FILE|INVRT_URL|INVRT_ENVIRONMENT|INVRT_PROFILE|INVRT_DEVICE|INVRT_*)
        unset "$name"
        ;;
    esac
  done < <(env)
}

setup_invrt_test() {
  clear_invrt_runtime_env

  export TEST_ROOT_BASE="$(choose_test_root)"
  export TEST_SUITE_NAME="$(basename "$BATS_TEST_FILENAME" .bats)"
  export TEST_NAME_SLUG="$(slugify "$BATS_TEST_NAME")"
  export TEST_ROOT="$TEST_ROOT_BASE/$TEST_SUITE_NAME/$TEST_NAME_SLUG"
  export TEST_DIR="$TEST_ROOT/project"
  export TEST_LOG_DIR="$TEST_ROOT/logs"
  export RUN_INDEX=0

  rm -rf "$TEST_ROOT"
  mkdir -p "$TEST_DIR" "$TEST_LOG_DIR"

  export INVRT_CWD="$TEST_DIR"

  printf '%s\n' "$BATS_TEST_NAME" > "$TEST_ROOT/test-name.txt"
  printf '%s\n' "$TEST_ROOT_BASE" > "$TEST_ROOT/test-root-base.txt"
}

teardown_invrt_test() {
  stop_fixture_server
  clear_invrt_runtime_env
}

write_runner_script() {
  local runner="$1"
  shift

  {
    printf '#!/usr/bin/env bash\n'
    printf 'exec '
    printf '%q ' "$@"
    printf '\n'
  } > "$runner"

  chmod +x "$runner"
}

record_run() {
  local prefix

  RUN_INDEX=$((RUN_INDEX + 1))
  prefix="$(printf '%s/%02d' "$TEST_LOG_DIR" "$RUN_INDEX")"
  export LAST_RUN_PREFIX="$prefix"

  printf '%s\n' "${RUN_COMMAND:-}" > "${prefix}.cmd"
  printf '%s\n' "$status" > "${prefix}.status"
  printf '%s' "$output" > "${prefix}.out"
}

run_invrt() {
  local runner="$TEST_LOG_DIR/run-$((RUN_INDEX + 1)).sh"

  write_runner_script "$runner" "$APP_BIN" "$@"
  export RUN_COMMAND="$runner"

  run "$runner"
  record_run
}

run_invrt_with_stdin() {
  local input="$1"
  shift

  local stdin_file="$TEST_LOG_DIR/stdin-$((RUN_INDEX + 1)).txt"
  local runner="$TEST_LOG_DIR/run-$((RUN_INDEX + 1)).sh"

  printf '%s' "$input" > "$stdin_file"
  write_runner_script "$runner" "$APP_BIN" "$@"
  export RUN_COMMAND="$runner < $stdin_file"

  run bash -lc '"$1" < "$2"' _ "$runner" "$stdin_file"
  record_run
}

run_invrt_in_tty_with_stdin() {
  local input="$1"
  shift

  local stdin_file="$TEST_LOG_DIR/stdin-$((RUN_INDEX + 1)).txt"
  local runner="$TEST_LOG_DIR/run-$((RUN_INDEX + 1)).sh"

  printf '%s' "$input" > "$stdin_file"
  write_runner_script "$runner" "$APP_BIN" "$@"
  export RUN_COMMAND="script -qec $runner /dev/null < $stdin_file"

  run bash -lc 'script -qec "$1" /dev/null < "$2"' _ "$runner" "$stdin_file"
  record_run
}

assert_output_contains() {
  [[ "$output" == *"$1"* ]]
}

assert_output_not_contains() {
  [[ "$output" != *"$1"* ]]
}

assert_file_exists() {
  [ -f "$1" ]
}

assert_dir_exists() {
  [ -d "$1" ]
}

assert_file_contains() {
  grep -Fq "$2" "$1"
}

assert_file_not_contains() {
  ! grep -Fq "$2" "$1"
}

yaml_get() {
  php -r '
    require $argv[1] . "/vendor/autoload.php";
    $data = Symfony\Component\Yaml\Yaml::parseFile($argv[2]);
    $value = $data;
    foreach (explode(".", $argv[3]) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            fwrite(STDERR, "Missing key: " . $argv[3] . PHP_EOL);
            exit(1);
        }
        $value = $value[$segment];
    }
    if (is_bool($value)) {
        echo $value ? "true" : "false";
        exit(0);
    }
    if (is_array($value)) {
        echo json_encode($value);
        exit(0);
    }
    echo (string) $value;
  ' "$APP_ROOT" "$1" "$2"
}

assert_yaml_equals() {
  local actual
  actual="$(yaml_get "$1" "$2")"
  [ "$actual" = "$3" ]
}

count_pngs() {
  find "$1" -type f -name '*.png' | wc -l | tr -d '[:space:]'
}

assert_png_count_at_least() {
  local count
  count="$(count_pngs "$1")"
  [ "${count:-0}" -ge "$2" ]
}

pick_free_port() {
  local port

  while true; do
    port="$(shuf -i 41000-48999 -n 1)"

    if ! bash -lc "exec 3<>/dev/tcp/127.0.0.1/$port" >/dev/null 2>&1; then
      printf '%s' "$port"
      return
    fi
  done
}

wait_for_http() {
  local url="$1"
  local attempts="${2:-40}"
  local i

  for ((i = 1; i <= attempts; i++)); do
    if curl --silent --fail "$url" >/dev/null 2>&1; then
      return 0
    fi
    sleep 0.25
  done

  return 1
}

start_fixture_server() {
  local website_dir="$APP_ROOT/tests/fixtures/website"

  export SERVER_PORT="$(pick_free_port)"
  export SERVER_URL="http://127.0.0.1:$SERVER_PORT"
  export SERVER_LOG="$TEST_LOG_DIR/php-server.log"

  php -S "127.0.0.1:$SERVER_PORT" -t "$website_dir" > "$SERVER_LOG" 2>&1 &
  export SERVER_PID=$!

  printf '%s\n' "$SERVER_PID" > "$TEST_ROOT/server.pid"
  printf '%s\n' "$SERVER_PORT" > "$TEST_ROOT/server.port"
  printf '%s\n' "$SERVER_URL" > "$TEST_ROOT/server.url"

  if ! wait_for_http "$SERVER_URL/"; then
    cat "$SERVER_LOG" >&2
    return 1
  fi
}

stop_fixture_server() {
  if [ -n "${SERVER_PID:-}" ] && kill -0 "$SERVER_PID" 2>/dev/null; then
    kill "$SERVER_PID"
    wait "$SERVER_PID" 2>/dev/null || true
  fi

  unset SERVER_PID SERVER_PORT SERVER_URL SERVER_LOG
}

seed_basic_config() {
  local url="$1"
  local environment="${2:-local}"
  local profile="${3:-anonymous}"
  local device="${4:-desktop}"

  mkdir -p "$TEST_DIR/.invrt"

  cat > "$TEST_DIR/.invrt/config.yaml" <<EOF
project:
  name: Test Project
environments:
  $environment:
    url: $url
profiles:
  $profile: {}
devices:
  $device: {}
EOF
}

seed_crawled_urls() {
  local environment="${1:-local}"
  local profile="${2:-anonymous}"
  shift 2

  local crawl_dir="$TEST_DIR/.invrt/data/$environment/$profile"
  mkdir -p "$crawl_dir"

  if [ "$#" -eq 0 ]; then
    : > "$crawl_dir/crawled_urls.txt"
    return
  fi

  printf '%s\n' "$@" > "$crawl_dir/crawled_urls.txt"
}
