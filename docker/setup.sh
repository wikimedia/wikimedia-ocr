#!/bin/bash

set -e

echo "Building image"
docker build -t wikimedia-ocr .

echo "Running composer install && npm install"
docker run --mount type=bind,source="$(pwd)",target=/wikimedia-ocr wikimedia-ocr:latest bash ./docker/install.sh

echo $'\e[1;32m'Everything looks good. Run ./docker/run.sh and an instance should be available at the default port \(8000\) $'\e[0m'
