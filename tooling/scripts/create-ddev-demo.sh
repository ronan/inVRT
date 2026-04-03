#!/bin/bash

set -e

DEMO_NAME="ddev-demo"

if [ -d "scratch/$DEMO_NAME" ]; then
    rm -rf "scratch/$DEMO_NAME"
fi
mkdir -p scratch/$DEMO_NAME
cd "scratch/$DEMO_NAME"


ddev config --project-type=backdrop --project-name="$DEMO_NAME"
echo "Downloading bee"
ddev add-on get backdrop-ops/ddev-backdrop-bee
ddev start
echo "Downloading core"
ddev bee download-core
echo "Installing site"
ddev bee si --username=admin --password=Password123 --db-name=db --db-user=db --db-pass=db --db-host=db --auto
echo "Installing invrt addon"
ddev add-on get ../ddev-invrt
ddev restart
echo "DDEV invrt addon setup complete."
