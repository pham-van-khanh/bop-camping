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

case "$ENVIRONMENT" in
    production)
        APP_DIR="/var/www/production"
        BRANCH="feat/scaffold-laravel"
        ;;
    staging)
        APP_DIR="/var/www/staging"
        BRANCH="develop"
        ;;
    *)
        error "Unknown environment: $ENVIRONMENT"
        exit 1
        ;;
esac

RELEASES_DIR="$APP_DIR/releases"
SHARED_DIR="$APP_DIR/shared"

TIMESTAMP=$(date +%Y%m%d%H%M%S)
NEW_RELEASE="$RELEASES_DIR/$TIMESTAMP"

log "Environment : $ENVIRONMENT"
log "Branch      : $BRANCH"
log "Release     : $TIMESTAMP"

success "Initialization completed."