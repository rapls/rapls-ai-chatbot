#!/usr/bin/env bash
# Install optional git hooks for release safety.
# Usage: bash .ci/install-hooks.sh
set -euo pipefail

HOOK_DIR="$(git rev-parse --show-toplevel)/.git/hooks"

# pre-push hook: remind to run verify-release.sh when pushing tags
cat > "${HOOK_DIR}/pre-push" << 'HOOK'
#!/usr/bin/env bash
# Installed by .ci/install-hooks.sh — safe to remove.
# Reminds to run release verification when pushing a version tag.
while read local_ref local_sha remote_ref remote_sha; do
    if echo "$local_ref" | grep -qE '^refs/tags/v'; then
        echo ""
        echo "=================================================="
        echo "  Pushing version tag: $local_ref"
        echo "  Have you run:  bash .ci/verify-release.sh  ?"
        echo "=================================================="
        echo ""
        read -r -p "Continue push? [y/N] " answer < /dev/tty
        if [ "$answer" != "y" ] && [ "$answer" != "Y" ]; then
            echo "Push aborted. Run verify-release.sh first."
            exit 1
        fi
    fi
done
HOOK
chmod +x "${HOOK_DIR}/pre-push"

echo "Installed: pre-push hook (tag push reminder)"
echo "To remove: rm ${HOOK_DIR}/pre-push"
