#!/usr/bin/env bash
#
# Health check — hit the Laravel /up endpoint and confirm HTTP 200.
# Used standalone or from deploy.sh to gate a release (auto-rollback on fail).
#
# Usage: ./scripts/health-check.sh <url> [host_header] [retries] [delay_seconds]
#   url          e.g. http://127.0.0.1/up
#   host_header  optional Host header for name-based vhosts (real domain)
#   retries      number of attempts (default 5)
#   delay        seconds between attempts (default 3)

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/common.sh"

URL="${1:-}"
HOST_HEADER="${2:-}"
RETRIES="${3:-5}"
DELAY="${4:-3}"

if [[ -z "$URL" ]]; then
    error "Usage: ./scripts/health-check.sh <url> [host_header] [retries] [delay]"
    exit 1
fi

log "Health check: $URL${HOST_HEADER:+ (Host: $HOST_HEADER)}"

curl_args=(-s -o /dev/null -w "%{http_code}" --max-time 10)
if [[ -n "$HOST_HEADER" ]]; then
    curl_args+=(-H "Host: $HOST_HEADER")
fi

code=""
for ((i = 1; i <= RETRIES; i++)); do
    # `|| true` so a non-2xx / connection error does not trip `set -e`;
    # we inspect the captured status code instead.
    code="$(curl "${curl_args[@]}" "$URL" || true)"

    if [[ "$code" == "200" ]]; then
        success "Health check passed (HTTP $code) on attempt $i/$RETRIES."
        exit 0
    fi

    warning "Attempt $i/$RETRIES: got '${code:-no response}', retrying in ${DELAY}s..."
    sleep "$DELAY"
done

error "Health check FAILED after $RETRIES attempts (last: '${code:-no response}')."
exit 1
