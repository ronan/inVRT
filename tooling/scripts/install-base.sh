#!/bin/bash

set -e

SCRIPT_DIR=$(dirname -- "${BASH_SOURCE[0]}")

# Basic dependencies
"$SCRIPT_DIR/install-package.sh" curl wget git gpg ca-certificates task
