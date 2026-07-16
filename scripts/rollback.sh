#!/usr/bin/env bash
#
# Rollback — point `current` back to the previous release, reload PHP-FPM,
# and restart queue workers. Called by deploy.sh on a failed health check,
# or manually: ./scripts/rollback.sh [production|staging]

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/common.sh"

#######################################
# Environment + Config
#######################################

ENVIRONMENT="${1:-}"

if [[ -z "$ENVIRONMENT" ]]; then
    error "Usage: ./scripts/rollback.sh [production|staging]"
    exit 1
fi

CONFIG_FILE="$SCRIPT_DIR/environments/${ENVIRONMENT}.conf"

if [[ ! -f "$CONFIG_FILE" ]]; then
    error "Environment config not found: $CONFIG_FILE"
    exit 1
fi

source "$CONFIG_FILE"

RELEASES_DIR="$APP_DIR/releases"
CURRENT_LINK="$APP_DIR/current"

#######################################
# Find Previous Release
#######################################

log "Looking for a previous release to roll back to..."

CURRENT_RELEASE="$(readlink -f "$CURRENT_LINK" 2>/dev/null || true)"

PREVIOUS=""
# Releases newest-first; pick the newest COMPLETE release that is NOT the
# current target. A release missing artisan (e.g. an interrupted deploy) is
# skipped — rolling back into it would point nginx at a non-existent
# index.php and 404 the whole site.
while read -r rel; do
    [[ -z "$rel" ]] && continue
    rel_path="$RELEASES_DIR/${rel%/}"
    [[ "$(readlink -f "$rel_path")" == "$CURRENT_RELEASE" ]] && continue
    if [[ ! -f "$rel_path/artisan" || ! -f "$rel_path/public/index.php" ]]; then
        warning "Skipping incomplete release: $(basename "$rel_path")"
        continue
    fi
    PREVIOUS="$rel_path"
    break
done < <(cd "$RELEASES_DIR" && ls -1dt -- */ 2>/dev/null || true)

if [[ -z "$PREVIOUS" || ! -d "$PREVIOUS" ]]; then
    error "No complete previous release found — cannot roll back."
    exit 1
fi

#######################################
# Switch Back
#######################################

log "Rolling back:"
log "  from: ${CURRENT_RELEASE:-<none>}"
log "  to  : $PREVIOUS"

# Same guard as deploy.sh: never let ln nest a symlink inside a real `current` dir.
if [[ -d "$CURRENT_LINK" && ! -L "$CURRENT_LINK" ]]; then
    warning "'current' is a real directory — removing it so the symlink is created correctly."
    rm -rf "$CURRENT_LINK"
fi

ln -sfn "$PREVIOUS" "$CURRENT_LINK"

success "Symlink switched to previous release."

#######################################
# Reload PHP-FPM (non-fatal)
#######################################

log "Reloading PHP-FPM..."

if sudo -n systemctl reload "$PHP_FPM_SERVICE" 2>/dev/null; then
    success "PHP-FPM reloaded ($PHP_FPM_SERVICE)."
else
    warning "Could not reload $PHP_FPM_SERVICE (deploy user lacks sudo NOPASSWD)."
fi

#######################################
# Restart Queue Workers
#######################################

log "Restarting queue workers..."

cd "$CURRENT_LINK"
"$PHP_BIN" artisan queue:restart || warning "queue:restart failed (continuing)."

success "Rollback completed."
