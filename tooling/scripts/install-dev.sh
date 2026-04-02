#!/bin/bash

set -e

SCRIPT_DIR=$(dirname -- "${BASH_SOURCE[0]}")


curl -1sLf 'https://dl.cloudsmith.io/public/task/task/setup.deb.sh' | sudo -E bash

# Dev dependencies
"$SCRIPT_DIR/install-package.sh" curl wget git gpg ca-certificates task

task install:dev
# source "$SCRIPT_DIR/install-base.sh"
# source "$SCRIPT_DIR/install-node.sh"
# source "$SCRIPT_DIR/install-php.sh"

# Node.js dependencies for Playwright

# npx playwright install --with-deps

npm install
