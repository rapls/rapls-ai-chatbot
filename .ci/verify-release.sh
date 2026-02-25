#!/usr/bin/env bash
# Release verification script — run before pushing a release.
# Produces: git archive ZIP → allow-list check → SHA-256 → build label.
#
# Usage: bash .ci/verify-release.sh [output-dir]
# Default output: /tmp
set -euo pipefail

PLUGIN_SLUG="rapls-ai-chatbot"
OUTPUT_DIR="${1:-/tmp}"
ZIP_PATH="${OUTPUT_DIR}/${PLUGIN_SLUG}-verify.zip"
COMMIT=$(git rev-parse --short HEAD)
BRANCH=$(git rev-parse --abbrev-ref HEAD)

echo "=== Release Verification: ${PLUGIN_SLUG} ==="
echo "Commit: ${COMMIT} (${BRANCH})"
echo ""

# 1. Build ZIP via git archive
git archive --format=zip HEAD -o "${ZIP_PATH}"
echo "1. ZIP created: ${ZIP_PATH}"

# 2. Allow-list check (mirrors CI)
ALLOWED="^${PLUGIN_SLUG}/(assets/|includes/|languages/|templates/|${PLUGIN_SLUG}\\.php|readme\\.txt|uninstall\\.php|\$)"
ENTRIES=$(unzip -l "${ZIP_PATH}" | awk 'NR>3 && /^ / && NF>=4 {print $NF}' | grep -v '^-' | grep -v '^$')
UNEXPECTED=$(echo "$ENTRIES" | grep -vE "$ALLOWED" || true)
if [ -n "$UNEXPECTED" ]; then
    echo "2. FAIL: Unexpected files in ZIP:"
    echo "$UNEXPECTED"
    rm -f "${ZIP_PATH}"
    exit 1
fi
FILE_COUNT=$(echo "$ENTRIES" | grep -c '.' || true)
echo "2. Allow-list: OK (${FILE_COUNT} files)"

# 3. SHA-256
SHA=$(shasum -a 256 "${ZIP_PATH}" | awk '{print $1}')
echo "3. SHA-256: ${SHA}"

# 4. Build label from ZIP
BUILD=$(unzip -p "${ZIP_PATH}" "${PLUGIN_SLUG}/${PLUGIN_SLUG}.php" | grep -oP "WPAIC_BUILD.*?'\K[^']*" || echo "n/a")
VERSION=$(unzip -p "${ZIP_PATH}" "${PLUGIN_SLUG}/includes/version.php" | grep -oP "WPAIC_VERSION.*?'\K[^']*" || echo "n/a")
echo "4. Version: ${VERSION} | Build: ${BUILD}"

# 5. Dev file leak check
LEAKED=$(unzip -l "${ZIP_PATH}" | grep -iE '(CLAUDE\.md|\.DS_Store|node_modules|\.env|\.claude/)' || true)
if [ -n "$LEAKED" ]; then
    echo "5. WARNING: Possible dev file leak:"
    echo "$LEAKED"
else
    echo "5. Dev file leak check: OK"
fi

rm -f "${ZIP_PATH}"
echo ""
echo "=== Summary ==="
echo "Commit: ${COMMIT}"
echo "Version: ${VERSION}"
echo "SHA-256: ${SHA}"
echo "Status: PASS"
