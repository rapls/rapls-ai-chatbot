#!/bin/bash
# check-free-boundary.sh — Verify Free/Pro separation boundaries
ERRORS=0

check() {
    local label="$1" count="$2"
    if [ "$count" -eq 0 ]; then
        echo "✓ $label"
    else
        echo "✗ $label ($count)"
        ERRORS=$((ERRORS+1))
    fi
}

echo "=== Free Plugin Boundary Check ==="

check "WPAIC_ references" $(grep -rn 'WPAIC_' --include='*.php' --include='*.js' . | grep -v 'docs/\|\.git/' | wc -l | tr -d ' ')
check "is_pro() definition" $(grep -c 'function is_pro(' includes/class-extensions.php 2>/dev/null || echo 0)
check "pro_required" $(grep -rn 'pro_required' --include='*.php' --include='*.js' . | grep -v 'docs/' | wc -l | tr -d ' ')
check "Pro blocking gates" $(grep -rn 'check_ip_whitelist\|is_ip_blocked\|check_budget_limit' --include='*.php' . | grep -v 'docs/\|class-extensions' | wc -l | tr -d ' ')
check "CDN references" $(grep -rn 'cdnjs.cloudflare.com\|cdn.jsdelivr.net' --include='*.js' --include='*.php' . | grep -v 'docs/' | wc -l | tr -d ' ')

echo ""
[ "$ERRORS" -eq 0 ] && echo "All checks passed!" || { echo "$ERRORS check(s) failed!"; exit 1; }
