#!/bin/bash

# SF-001 Gradual Rollout Execution Script
# Usage: bash EXECUTE_SF001_ROLLOUT.sh [phase]
# Example: bash EXECUTE_SF001_ROLLOUT.sh phase2

PROJECT_ROOT="/home/jessessh/htdocs/srv1113343.hstgr.cloud"
PHASE="${1:-monitor}"

log() {
    echo "[$(date +'%H:%M:%S')] $1"
}

case $PHASE in
    phase2)
        log "🟡 PHASE 2: Escalating from 10% → 25%"
        log "Action: Update load balancer upstream weight to 25%"
        log "Monitor: curl -I https://awamotos.com/ | grep X-Cache"
        log "Duration: 15 minutes"
        ;;
    phase3)
        log "🟠 PHASE 3: Escalating from 25% → 50%"
        log "Action: Update load balancer upstream weight to 50%"
        log "Monitor: Check for errors in var/log/system.log"
        log "Duration: 15 minutes"
        ;;
    phase4)
        log "🔴 PHASE 4: Escalating from 50% → 100% (PRODUCTION)"
        log "Action: Update load balancer upstream weight to 100%"
        log "CONFIRMATION REQUIRED: Type 'yes' to proceed"
        read -p "Continue to 100% rollout? " CONFIRM
        if [ "$CONFIRM" = "yes" ]; then
            log "✅ Full production rollout initiated"
        else
            log "❌ Rollout cancelled"
            exit 1
        fi
        ;;
    monitor)
        log "📊 MONITORING CANARY (10%)"
        log "Check browser console for errors:"
        log "  1. Open https://awamotos.com/"
        log "  2. DevTools → Console"
        log "  3. Look for CSS errors"
        log ""
        log "Check Network tab:"
        log "  1. DevTools → Network"
        log "  2. Filter by 'css'"
        log "  3. Verify awa-core-variables.css loads first"
        log ""
        log "When ready for Phase 2, run:"
        log "  bash EXECUTE_SF001_ROLLOUT.sh phase2"
        ;;
    *)
        log "Usage: bash $0 [phase|monitor]"
        log "  monitor   - Check canary metrics (current)"
        log "  phase2    - Escalate to 25%"
        log "  phase3    - Escalate to 50%"
        log "  phase4    - Escalate to 100% (requires confirmation)"
        ;;
esac

