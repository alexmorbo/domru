#!/bin/bash
set -ev

VERSION=`cat config.json | jq -r '.version'`

echo "Running build for $VERSION"

docker buildx build --progress=plain --pull --push -t alexmorbo/domru-amd64:latest   -t alexmorbo/domru-amd64:$VERSION   -f Dockerfile.amd64   .
docker buildx build --progress=plain --pull --push -t alexmorbo/domru-armv7:latest   -t alexmorbo/domru-armv7:$VERSION   -f Dockerfile.armv7   .
docker buildx build --progress=plain --pull --push -t alexmorbo/domru-aarch64:latest -t alexmorbo/domru-aarch64:$VERSION -f Dockerfile.aarch64 .
docker buildx build --progress=plain --pull --push -t alexmorbo/domru-i386:latest    -t alexmorbo/domru-i386:$VERSION    -f Dockerfile.i386    .
