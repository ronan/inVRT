#!/bin/bash

set -e

if ! dpkg -s "$@" > /dev/null 2>&1; then
    
    echo "Running apt-get update..."
    apt-get update -y

    echo "Installing packages: $@ ..."
    apt-get -y install --no-install-recommends "$@"
fi
