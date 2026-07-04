#!/usr/bin/env bash

set -Eeuo pipefail

#######################################
# Colors
#######################################

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

#######################################
# Logging
#######################################

log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
}

success() {
    echo -e "${GREEN}✔ $1${NC}"
}

warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

error() {
    echo -e "${RED}✘ $1${NC}"
}

#######################################
# Exit Handler
#######################################

on_error() {
    error "Deployment failed at line $1"
}

trap 'on_error $LINENO' ERR