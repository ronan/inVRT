#!/usr/bin/env bash

set -euo pipefail


VERSION="$(node -p "require('./package.json').version")"
IMAGE="ronan4000/invrt"

echo "Publishing Docker image for version ${VERSION}"
echo "
> docker push "${IMAGE}:${VERSION}"
> docker push "${IMAGE}:latest"
"

if [[ -n "${DOCKERHUB_USERNAME:-}" && -n "${DOCKERHUB_TOKEN:-}" ]]; then
  echo "Logging in to Docker Hub as ${DOCKERHUB_USERNAME}"
  echo "${DOCKERHUB_TOKEN}" | docker login -u "${DOCKERHUB_USERNAME}" --password-stdin >/dev/null
fi

echo "Publishing Docker tags: ${IMAGE}:${VERSION} and ${IMAGE}:latest"
docker push "${IMAGE}:${VERSION}"
docker push "${IMAGE}:latest"
