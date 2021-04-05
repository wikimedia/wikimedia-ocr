#!/bin/bash

docker run -p ${DOCKER_OCR_PORT:-8000}:8000 --mount type=bind,source="$(pwd)",target=/wikimedia-ocr wikimedia-ocr:latest
