#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PACKAGE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
REPO_ROOT="$(cd "${PACKAGE_DIR}/.." && pwd)"
FIXTURE_DIR="${REPO_ROOT}/invrt"
INDEX_HTML="${FIXTURE_DIR}/index.html"

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "error: required command not found: $1" >&2
    exit 1
  fi
}

assert_file() {
  if [[ ! -f "$1" ]]; then
    echo "error: expected file was not created: $1" >&2
    exit 1
  fi
}

assert_grep() {
  local pattern="$1"
  local file="$2"
  local message="$3"
  if ! grep -Eq "$pattern" "$file"; then
    echo "error: ${message}" >&2
    exit 1
  fi
}

assert_not_grep() {
  local pattern="$1"
  local file="$2"
  local message="$3"
  if grep -Eq "$pattern" "$file"; then
    echo "error: ${message}" >&2
    exit 1
  fi
}

require_cmd node
require_cmd npm
require_cmd npx
require_cmd grep

if [[ ! -d "$FIXTURE_DIR" ]]; then
  echo "error: fixture directory not found: $FIXTURE_DIR" >&2
  exit 1
fi

if [[ ! -f "${FIXTURE_DIR}/plan.yaml" ]]; then
  echo "error: missing fixture plan: ${FIXTURE_DIR}/plan.yaml" >&2
  exit 1
fi

if [[ ! -f "${FIXTURE_DIR}/report.json" ]]; then
  echo "error: missing fixture report: ${FIXTURE_DIR}/report.json" >&2
  exit 1
fi

echo "==> Installing invrt-reporter dependencies"
(
  cd "$PACKAGE_DIR"
  npm install
)

echo "==> Building invrt-reporter package"
(
  cd "$PACKAGE_DIR"
  npm run build
)

echo "==> Building fixture report into ${FIXTURE_DIR}"
(
  cd "$PACKAGE_DIR"
  INVRT_PLAN="${FIXTURE_DIR}/plan.yaml" \
  INVRT_RESULTS="${FIXTURE_DIR}/report.json" \
  INVRT_VERSION="$(node -p "require('./package.json').version")" \
  npx astro build --outDir "$FIXTURE_DIR"
)

echo "==> Verifying generated output"
assert_file "$INDEX_HTML"
assert_grep '<style' "$INDEX_HTML" "index.html does not contain inline styles"
assert_grep '<script[^>]*>' "$INDEX_HTML" "index.html does not contain inline scripts"
assert_grep 'approved/' "$INDEX_HTML" "index.html does not reference approved screenshots"
assert_grep 'results/' "$INDEX_HTML" "index.html does not reference results screenshots"
assert_grep 'data-detail-panel' "$INDEX_HTML" "detail panel markup is missing"
assert_grep 'href="#page-' "$INDEX_HTML" "single-page detail links are missing"
assert_not_grep '<script[^>]+src=' "$INDEX_HTML" "index.html still references external script assets"
assert_not_grep '<link[^>]+rel="stylesheet"' "$INDEX_HTML" "index.html still references external stylesheet assets"
assert_not_grep 'src="/(approved|results)/' "$INDEX_HTML" "image paths must stay relative, not root-absolute"
assert_not_grep 'href="/(approved|results)/' "$INDEX_HTML" "asset links must stay relative, not root-absolute"
assert_not_grep '/_astro/' "$INDEX_HTML" "index.html still references Astro asset files"

echo
echo "Validation passed:"
echo "  - invrt-reporter installs and builds"
echo "  - fixture report builds into ${FIXTURE_DIR}"
echo "  - ${INDEX_HTML} exists"
echo "  - CSS/JS are inline in the generated HTML"
echo "  - screenshot paths remain relative"
echo "  - single-page detail UI markup is present"
