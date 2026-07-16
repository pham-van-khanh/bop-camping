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
# Frontend Build
#######################################

log "Installing Node.js dependencies..."

npm ci --no-audit --no-fund

success "Node.js dependencies installed."

log "Building frontend assets..."

npm run build

success "Frontend assets built."

success "Step completed."

#######################################
# Link Shared Files
#######################################

log "Linking shared files..."

rm -rf "$NEW_RELEASE/storage"
ln -s "$SHARED_DIR/storage" "$NEW_RELEASE/storage"

ln -sf "$SHARED_DIR/.env" "$NEW_RELEASE/.env"

success "Shared files linked."

#######################################
# Link Public Storage
#######################################

log "Linking public storage..."

cd "$NEW_RELEASE"

# public/ is fresh from git each release, so public/storage never exists yet;
# recreate the symlink -> ../storage/app/public (storage already points at shared).
"$PHP_BIN" artisan storage:link

success "Public storage linked."

#######################################
# Laravel Optimize
#######################################

log "Optimizing Laravel..."

cd "$NEW_RELEASE"

"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan cache:clear
"$PHP_BIN" artisan route:clear
"$PHP_BIN" artisan view:clear

"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

success "Laravel optimized."

#######################################
# Database Migration
#######################################

log "Running database migrations..."

cd "$NEW_RELEASE"

"$PHP_BIN" artisan migrate --force

success "Database migrations completed."

#######################################
# Switch Current Release
#######################################

log "Switching current release..."

# If `current` is a real directory (e.g. left from a manual setup), `ln -sfn`
# would nest the symlink INSIDE it instead of replacing it, leaving nginx's
# `current/public` root missing -> 404. Remove the stray dir first so we always
# end up with a proper symlink.
if [[ -d "$APP_DIR/current" && ! -L "$APP_DIR/current" ]]; then
    warning "'current' is a real directory — removing it so the symlink is created correctly."
    rm -rf "$APP_DIR/current"
fi

ln -sfn "$NEW_RELEASE" "$APP_DIR/current"

success "Current release switched."

#######################################
# Reload PHP-FPM
#######################################
# OPcache is ON: PHP-FPM caches the resolved path of `current` in its realpath
# cache, so it keeps serving the OLD release until that cache expires
# (~realpath_cache_ttl, default 120s). Reloading makes the switch instant.
# Non-fatal: if the deploy user lacks sudo NOPASSWD we only warn.

log "Reloading PHP-FPM..."

if sudo -n systemctl reload "$PHP_FPM_SERVICE" 2>/dev/null; then
    success "PHP-FPM reloaded ($PHP_FPM_SERVICE)."
else
    warning "Could not reload $PHP_FPM_SERVICE (deploy user lacks sudo NOPASSWD)."
    warning "New release will go live once realpath cache expires (~120s)."
    warning "Configure sudoers to enable an instant switch — see scripts/environments."
fi

#######################################
# Health Check (auto-rollback on failure)
#######################################

log "Running health check on the new release..."

# Temporarily disable the ERR trap: a failed health check is handled here
# (rollback), it is not an unexpected script error.
set +e
bash "$SCRIPT_DIR/health-check.sh" \
    "$HEALTH_URL" \
    "${HEALTH_HOST:-}" \
    "${HEALTH_RETRIES:-5}" \
    "${HEALTH_DELAY:-3}"
HEALTH_STATUS=$?
set -e

if [[ "$HEALTH_STATUS" -ne 0 ]]; then
    error "Health check failed — rolling back to the previous release."

    set +e
    bash "$SCRIPT_DIR/rollback.sh" "$ENVIRONMENT"
    ROLLBACK_STATUS=$?
    set -e

    if [[ "$ROLLBACK_STATUS" -ne 0 ]]; then
        error "Rollback also failed — MANUAL INTERVENTION REQUIRED."
    else
        warning "Rolled back to previous release. New release NOT live."
    fi

    exit 1
fi

success "Health check passed — new release is healthy."

#######################################
# Restart Queue Workers
#######################################

if [[ "${QUEUE_RESTART:-false}" == "true" ]]; then
    log "Restarting queue workers..."

    cd "$APP_DIR/current"

    "$PHP_BIN" artisan queue:restart

    success "Queue restart signal sent."
else
    log "Queue restart disabled (QUEUE_RESTART != true), skipping."
fi

#######################################
# Cleanup Releases
#######################################

log "Cleaning old releases..."

bash "$SCRIPT_DIR/cleanup.sh" \
    "$APP_DIR" \
    "$KEEP_RELEASES"

success "Cleanup completed."

#######################################
# Finish
#######################################

success "Step completed."