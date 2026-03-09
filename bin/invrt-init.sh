#!/bin/bash

mkdir -p ./invrt/data/bitmaps ./invrt/data/reports ./invrt/data/logs ./invrt/data/clones ./invrt/profiles ./invrt/scripts
echo "Created data directories for images, report, logs, clones, profiles, and scripts."

echo "
# InVRT Configuration File
# This file is used to store configuration settings for InVRT.
# You can customize the settings below as needed.

project:
  name: My InVRT Project
  url: http://example.com
  description: A description of your project.

settings:
  max_crawl_depth: 3
  max_pages: 100
  max_concurrent_requests: 5
  user_agent: InVRT/1.0

environments:
  local:
    name: Local
    url: http://localhost

  dev:
    name: Development
    url: https://dev.example.com
    credentials:

  prod:
    name: Production
    url: https://prod.example.com

profiles:
  default:
    name: Default Profile
    description: A default profile for testing.

  admin:
    name: Admin Profile
    description: A profile with admin privileges.
    auth:
      username: admin
      password: password123

viewports:
  default:
    name: Default Viewport
    description: A desktop sized viewport for testing.
    width: 1920
    height: 1080

  mobile:
    name: Mobile Viewport
    description: A viewport for mobile testing.
    width: 375
    height: 667

" > ./invrt/config.yaml
echo "Initialized InVRT configuration file at ./.config.yaml"

echo "# Add urls to this file to exclude them from testing. Regex patterns are supported. For example:

/do-not-crawl-this-url
/do-not-crawl-this-directory/.*

" > ./invrt/data/exclude_urls.txt