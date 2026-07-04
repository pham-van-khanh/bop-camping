#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="$1"
KEEP_RELEASES="$2"

RELEASES_DIR="$APP_DIR/releases"

cd "$RELEASES_DIR"

ls -1dt */ | tail -n +$((KEEP_RELEASES + 1)) | while read -r release
do
    echo "Removing old release: $release"
    rm -rf "$RELEASES_DIR/$release"
done