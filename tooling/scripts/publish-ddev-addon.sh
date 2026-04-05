#!/bin/bash

set -euo pipefail

if [[ -z "${GITHUB_TOKEN:-}" ]]; then
    echo "Missing GITHUB_TOKEN"
    exit 1
fi

VERSION="$(node -p "require('./package.json').version")"
TAG="v${VERSION}"
REPO_URL="https://x-access-token:${GITHUB_TOKEN}@github.com/ronan/ddev-invrt.git"

if [ -d "scratch/ddev-invrt" ]; then
    rm -rf scratch/ddev-invrt
fi

git clone "$REPO_URL" scratch/ddev-invrt
rsync -a --delete ddev-invrt/ scratch/ddev-invrt/

cd scratch/ddev-invrt

git config user.name "${GIT_AUTHOR_NAME:-invrt-release-bot}"
git config user.email "${GIT_AUTHOR_EMAIL:-invrt-release-bot@users.noreply.github.com}"

git add .

if [[ -n "$(git status --porcelain)" ]]; then
    git commit -m "Release ${TAG}: sync ddev-invrt addon"
else
    echo "No addon content changes detected; skipping commit"
fi

if git rev-parse "$TAG" >/dev/null 2>&1; then
    echo "Tag ${TAG} already exists locally"
else
    git tag -a "$TAG" -m "Release ${TAG}"
fi

git push origin main
git push origin "$TAG"
cd ../..
rm -rf scratch/ddev-invrt
