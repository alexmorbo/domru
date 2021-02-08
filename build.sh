#!/bin/bash
set -ev

VERSION=`cat domru/config.json | jq -r '.version'`

echo "Running build for $VERSION"

# build
docker build -t alexmorbo/domru-amd64:latest   -t alexmorbo/domru-amd64:$VERSION   -f domru/Dockerfile.amd64   ./domru
docker build -t alexmorbo/domru-armv7:latest   -t alexmorbo/domru-armv7:$VERSION   -f domru/Dockerfile.armv7   ./domru
docker build -t alexmorbo/domru-aarch64:latest -t alexmorbo/domru-aarch64:$VERSION -f domru/Dockerfile.aarch64 ./domru
docker build -t alexmorbo/domru-i386:latest    -t alexmorbo/domru-i386:$VERSION    -f domru/Dockerfile.i386    ./domru

# push
docker push alexmorbo/domru-amd64:latest
docker push alexmorbo/domru-amd64:$VERSION

docker push alexmorbo/domru-armv7:latest
docker push alexmorbo/domru-armv7:$VERSION

docker push alexmorbo/domru-aarch64:latest
docker push alexmorbo/domru-aarch64:$VERSION

docker push alexmorbo/domru-i386:latest
docker push alexmorbo/domru-i386:$VERSION
