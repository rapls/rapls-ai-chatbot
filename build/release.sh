#!/bin/bash
#
# Build release ZIPs for Free and Pro plugins.
# Reads .distignore from each plugin root to determine exclusions.
# Verifies no dangerous files are included in the final ZIPs.
#
# Usage:
#   cd /path/to/plugins/rapls-ai-chatbot
#   bash build/release.sh
#
# Output:
#   ../rapls-ai-chatbot.zip
#   ../rapls-ai-chatbot-pro.zip

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
FREE_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PLUGINS_DIR="$(cd "$FREE_DIR/.." && pwd)"
PRO_DIR="$PLUGINS_DIR/rapls-ai-chatbot-pro"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

error() { echo -e "${RED}ERROR: $1${NC}" >&2; }
success() { echo -e "${GREEN}$1${NC}"; }
warn() { echo -e "${YELLOW}WARNING: $1${NC}"; }

# Build one plugin ZIP using rsync + zip for reliable .distignore support
build_zip() {
    local plugin_dir="$1"
    local plugin_name
    plugin_name="$(basename "$plugin_dir")"
    local output="$PLUGINS_DIR/${plugin_name}.zip"
    local distignore="$plugin_dir/.distignore"
    local tmpdir

    echo ""
    echo "=== Building ${plugin_name}.zip ==="

    if [[ ! -f "$distignore" ]]; then
        error ".distignore not found in $plugin_dir"
        return 1
    fi

    # Remove old ZIP if exists
    rm -f "$output"

    # Use a temp directory for clean copy
    tmpdir="$(mktemp -d)"
    trap "rm -rf '$tmpdir'" RETURN

    # rsync with --exclude-from for reliable pattern matching
    rsync -a \
        --exclude-from="$distignore" \
        --exclude="build/" \
        "$plugin_dir/" "$tmpdir/$plugin_name/"

    # Create ZIP from temp directory
    (cd "$tmpdir" && zip -r "$output" "$plugin_name") > /dev/null

    rm -rf "$tmpdir"
    trap - RETURN

    success "Created: $output"
}

# Verify no dangerous files are included in a ZIP
verify_zip() {
    local zip_file="$1"
    local plugin_name="$2"
    local has_error=0

    echo "  Verifying ${plugin_name}.zip ..."

    # Dangerous patterns that must NOT be in the ZIP
    local dangerous_patterns=(
        "rpls-license-config.php"
        "rpls-license-api.php"
        "rpls-license-server.zip"
        "license-generator/"
        "update-server/rapls-ai-chatbot-pro.zip"
        "update-server/api.php"
        "/\.git/"
        "/\.github/"
        "/\.serena/"
        "/\.claude/"
        "CLAUDE\.md"
    )

    local zip_contents
    zip_contents="$(zipinfo -1 "$zip_file")"

    for pattern in "${dangerous_patterns[@]}"; do
        if echo "$zip_contents" | grep -qE "$pattern"; then
            error "  DANGEROUS FILE FOUND: $pattern"
            has_error=1
        fi
    done

    if [[ $has_error -eq 1 ]]; then
        error "  ZIP verification FAILED for $plugin_name"
        return 1
    fi

    local file_count
    file_count="$(zipinfo -1 "$zip_file" | wc -l | tr -d ' ')"
    success "  Verified: No dangerous files found ($file_count entries)"
    return 0
}

# ---- Main ----

echo "Release Build Script"
echo "===================="

build_ok=0
verify_ok=0

# Build Free plugin
if [[ -d "$FREE_DIR" ]]; then
    build_zip "$FREE_DIR" && build_ok=$((build_ok + 1))
    verify_zip "$PLUGINS_DIR/rapls-ai-chatbot.zip" "rapls-ai-chatbot" && verify_ok=$((verify_ok + 1))
else
    warn "Free plugin directory not found: $FREE_DIR"
fi

# Build Pro plugin
if [[ -d "$PRO_DIR" ]]; then
    build_zip "$PRO_DIR" && build_ok=$((build_ok + 1))
    verify_zip "$PLUGINS_DIR/rapls-ai-chatbot-pro.zip" "rapls-ai-chatbot-pro" && verify_ok=$((verify_ok + 1))
else
    warn "Pro plugin directory not found: $PRO_DIR"
fi

echo ""
if [[ $verify_ok -eq $build_ok && $build_ok -gt 0 ]]; then
    success "Release build complete! ($build_ok ZIPs built and verified)"
else
    error "Release build finished with errors."
    exit 1
fi
