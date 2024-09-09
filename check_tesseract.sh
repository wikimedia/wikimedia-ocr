#!/bin/bash

set -euo pipefail

# Note, this assumes that the output of `tesseract --version` will remain consistent.
MIN_TESSERACT_VERSION="tesseract 4"

if [ -n "${DISABLE_TESSERACT_CHECK+placeholder}" ]; then
  echo "DISABLE_TESSERACT_CHECK is set, skipping tesseract check."
  exit 0
fi

echo "Checking tesseract installation"

if ! type tesseract &> /dev/null; then
  echo "Tesseract not found!"
  exit 1
else
  echo "Tesseract executable OK"
fi

# Similar to what tesseract-ocr-for-php does
CUR_TESSERACT_VERSION=$(tesseract --version | head -n1 | sed "s/tesseract v/tesseract /")
CUR_MIN_VERSION=$( echo -e "$MIN_TESSERACT_VERSION\n$CUR_TESSERACT_VERSION" | sort -V | head -n1 )
if [ "$CUR_MIN_VERSION" != "$MIN_TESSERACT_VERSION" ]; then
  echo "Tesseract version mismatch: current is ${CUR_TESSERACT_VERSION}, minimum required is ${MIN_TESSERACT_VERSION}"
  exit 1
else
  echo "Tesseract version OK"
fi

# For the future, we might make languages optional; we'd probably have to cache the result of `tesseract --list-langs`.

if type jq &> /dev/null; then
  # Sort both just in case, and remove duplicates from the expected list to account for google having more variants that
  # map to the same code in tesseract (e.g. zh and zh-hans)
  # Skip deu_latf as it's not insalled by default yet (but will be in the future).
  AVAILABLE_LANGS=$(tesseract --list-langs | tail -n +2 | sort)
  EXPECTED_LANGS=$(jq -r '.tesseract | keys | to_entries[] | .value' public/models.json | sort -u | sed "/^deu_latf$/d" )

  EXTRA_LOCAL_LANGS=$( comm -23 <( echo "$AVAILABLE_LANGS" ) <( echo "$EXPECTED_LANGS" ) )
  MISSING_LOCAL_LANGS=$( comm -13 <( echo "$AVAILABLE_LANGS" ) <( echo "$EXPECTED_LANGS" ) )

  if [ -z "$MISSING_LOCAL_LANGS" ]; then
    echo "All expected languages are installed"
  else
    echo -e "The following required languages are not installed:\n$MISSING_LOCAL_LANGS"
    exit 1
  fi
  if [ -n "$EXTRA_LOCAL_LANGS" ]; then
    echo -e "The following languages are installed but not supported:\n$EXTRA_LOCAL_LANGS"
  fi
else
  echo "jq is not installed, skipping validation of available languages"
fi

echo "All checks passed!"
