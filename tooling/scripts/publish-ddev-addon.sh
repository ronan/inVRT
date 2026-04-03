#!/bin/bash

set -e

if [ -d "scratch/ddev-invrt" ]; then
    rm -rf scratch/ddev-invrt
fi
git clone https://github.com/ronan/ddev-invrt scratch/ddev-invrt
cp -r ddev-invrt/* scratch/ddev-invrt/

cd scratch/ddev-invrt
git add .
git commit -m "Auto-publish invrt-ddev addon to github"

git push origin main
cd ../..
rm -rf scratch/ddev-invrt
