#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/common.sh"

#######################################
# Environment
#######################################

ENVIRONMENT="${1:-}"

if [[ -z "$ENVIRONMENT" ]]; then
    error "Usage: ./scripts/deploy.sh [production|staging]"
    exit 1
fi

#######################################
# Load Configuration
#######################################

CONFIG_FILE="$SCRIPT_DIR/environments/${ENVIRONMENT}.conf"

if [[ ! -f "$CONFIG_FILE" ]]; then
    error "Environment config not found: $CONFIG_FILE"
    exit 1
fi

source "$CONFIG_FILE"

#######################################
# Derived Variables
#######################################

RELEASES_DIR="$APP_DIR/releases"
SHARED_DIR="$APP_DIR/shared"

TIMESTAMP=$(date +"%Y%m%d%H%M%S")
NEW_RELEASE="$RELEASES_DIR/$TIMESTAMP"

#######################################
# Deployment Information
#######################################

log "==========================================="
log "Laravel Deployment"
log "==========================================="
log "Environment : $ENV_NAME"
log "Repository  : $REPOSITORY"
log "Branch      : $BRANCH"
log "App Dir     : $APP_DIR"
log "Release     : $TIMESTAMP"
log "==========================================="

#######################################
# Pre-flight Check
#######################################

log "Running pre-flight checks..."

[[ -d "$APP_DIR" ]] || {
    error "Application directory not found: $APP_DIR"
    exit 1
}

[[ -d "$RELEASES_DIR" ]] || {
    error "Releases directory not found: $RELEASES_DIR"
    exit 1
}

[[ -d "$SHARED_DIR" ]] || {
    error "Shared directory not found: $SHARED_DIR"
    exit 1
}

command -v "$PHP_BIN" >/dev/null 2>&1 || {
    error "PHP executable not found: $PHP_BIN"
    exit 1
}

command -v git >/dev/null 2>&1 || {
    error "Git is not installed."
    exit 1
}

command -v "$COMPOSER_BIN" >/dev/null 2>&1 || {
    error "Composer executable not found: $COMPOSER_BIN"
    exit 1
}

command -v "$NPM_BIN" >/dev/null 2>&1 || {
    error "NPM executable not found: $NPM_BIN"
    exit 1
}

success "Pre-flight checks passed."

#######################################
# Create Release Directory
#######################################

log "Creating release directory..."

if [[ -d "$NEW_RELEASE" ]]; then
    error "Release already exists: $NEW_RELEASE"
    exit 1
fi

mkdir -p "$NEW_RELEASE"

success "Release directory created:"
log "$NEW_RELEASE"

#######################################
# Verify Release
#######################################

[[ -d "$NEW_RELEASE" ]] || {
    error "Failed to create release directory."
    exit 1
}

success "Release directory verified."

#######################################
# Clone Repository
#######################################

log "Cloning repository..."

git clone \
    --branch "$BRANCH" \
    --depth 1 \
    "$REPOSITORY" \
    "$NEW_RELEASE"

success "Repository cloned."

#######################################
# Verify Source
#######################################

[[ -f "$NEW_RELEASE/artisan" ]] || {
    error "Laravel source is invalid."
    exit 1
}

success "Laravel source verified."

#######################################
# Composer Install
#######################################

log "Installing Composer dependencies..."

cd "$NEW_RELEASE"

"$COMPOSER_BIN" install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

success "Composer dependencies installed."

#######################################
# Finish
#######################################

success "Step completed."