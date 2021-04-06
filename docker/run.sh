#!/bin/bash

docker run -p ${DOCKER_OCR_PORT:-8000}:8000 --mount type=bind,source="$(cd "$(dirname $0)/.."; pwd)",target=/wikimedia-ocr wikimedia-ocr:latest
