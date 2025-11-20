#!/usr/bin/env bash
# tests/ci_check_ffmpeg.sh
# Verifies ffmpeg is available and can report version. Exit non-zero on failure.

set -e

echo "Checking ffmpeg..."
if ! command -v ffmpeg >/dev/null 2>&1; then
  echo "ffmpeg not found on PATH"
  exit 2
fi

ffmpeg -version | head -n 1
echo "ffmpeg appears available"
exit 0