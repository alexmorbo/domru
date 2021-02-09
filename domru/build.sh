#!/bin/bash
set -ev

VERSION=`cat config.json | jq -r '.version'`

echo "Running build for $VERSION"

# build
docker build -t alexmorbo/domru-amd64:latest   -t alexmorbo/domru-amd64:$VERSION   -f Dockerfile.amd64   .
# push
docker push alexmorbo/domru-amd64:latest
docker push alexmorbo/domru-amd64:$VERSION

docker build -t alexmorbo/domru-armv7:latest   -t alexmorbo/domru-armv7:$VERSION   -f Dockerfile.armv7   .
docker push alexmorbo/domru-armv7:latest
docker push alexmorbo/domru-armv7:$VERSION

docker build -t alexmorbo/domru-aarch64:latest -t alexmorbo/domru-aarch64:$VERSION -f Dockerfile.aarch64 .
docker push alexmorbo/domru-aarch64:latest
docker push alexmorbo/domru-aarch64:$VERSION

docker build -t alexmorbo/domru-i386:latest    -t alexmorbo/domru-i386:$VERSION    -f Dockerfile.i386    .
docker push alexmorbo/domru-i386:latest
docker push alexmorbo/domru-i386:$VERSION
