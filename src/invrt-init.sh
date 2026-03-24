#!/bin/bash

echo "🚀 Initializing InVRT for the project at $INIT_CWD"

if [ -d "$INIT_CWD/.invrt" ]; then
    echo "⚠️  InVRT is already initialized for this project. Please remove the .invrt directory if you want to re-initialize."
    exit 1
fi

mkdir -p $INVRT_DIRECTORY
echo "Created invrt directory at $INVRT_DIRECTORY"
cd $INVRT_DIRECTORY
mkdir -p data scripts
echo "Created data directories for genrated data, and user scripts."

echo "
# InVRT Configuration File
# This file is used to store configuration settings for InVRT.
# You can customize the settings below as needed.

name: My InVRT Project

environments:
  local:
    name: Local
    url: http://localhost

  dev:
    name: Development
    url: https://dev.example.com

  prod:
    name: Production
    url: https://prod.example.com

profiles:
  anonymous:
    name: Anonymous Visitor Profile
    description: Test the site as an anonymous visitor with no special permissions.

  admin:
    name: Admin Profile
    description: A profile with admin privileges.
    username: admin
    password: password123

devices:
  desktop:
    name: Desktop Viewport
    description: A desktop sized viewport for testing.
    viewport_width: 1920
    viewport_height: 1080

  mobile:
    name: Mobile Viewport
    description: A viewport for mobile testing.
    viewport_width: 375
    viewport_height: 667

" > config.yaml
echo "Initialized InVRT configuration file at $INVRT_DIRECTORY/config.yaml"

echo "/user/logout
/files
/sites
/core" > ./exclude_urls.txt