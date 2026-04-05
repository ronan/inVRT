#!/usr/bin/env bash

set -euo pipefail

if [[ -z "${DOCKERHUB_USERNAME:-}" || -z "${DOCKERHUB_TOKEN:-}" ]]; then
  echo "Missing DOCKERHUB_USERNAME or DOCKERHUB_TOKEN"
  exit 1
fi

VERSION="$(node -p "require('./package.json').version")"
IMAGE="ronan4000/invrt"

echo "Logging in to Docker Hub as ${DOCKERHUB_USERNAME}"
echo "${DOCKERHUB_TOKEN}" | docker login -u "${DOCKERHUB_USERNAME}" --password-stdin >/dev/null

echo "Publishing Docker tags: ${IMAGE}:${VERSION} and ${IMAGE}:latest"
docker push "${IMAGE}:${VERSION}"
docker push "${IMAGE}:latest"
