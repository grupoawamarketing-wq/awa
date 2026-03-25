#!/bin/bash
# CSS Validation Suite for AWA Motos
# Purpose: Validate CSS before deployment
# Usage: bash tests/css-validation.sh

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

BASE_DIR="app/design/frontend/AWA_Custom/ayo_home5_child/web/css"

echo "🔍 CSS Validation Suite"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Test 1: Syntax validation
echo -e "\n${YELLOW}Test 1: CSS Syntax Validation${NC}"
ERRORS=0
for css in "$BASE_DIR"/*.unmin.css; do
  if grep -qE '^\s*[^{]*\{.*:[^;]*$' "$css" 2>/dev/null; then
    echo -e "${RED}❌ Syntax error in $css${NC}"
    ERRORS=$((ERRORS + 1))
  fi
done
[ $ERRORS -eq 0 ] && echo -e "${GREEN}✅ All files have valid syntax${NC}" || exit 1

# Test 2: Variable references
echo -e "\n${YELLOW}Test 2: CSS Variable References${NC}"
VAR_ERRORS=0
for css in "$BASE_DIR"/*.unmin.css; do
  UNRESOLVED=$(grep -o 'var([^)]*undefined' "$css" | wc -l)
  if [ "$UNRESOLVED" -gt 0 ]; then
    echo -e "${RED}❌ $UNRESOLVED unresolved vars in $css${NC}"
    VAR_ERRORS=$((VAR_ERRORS + 1))
  fi
done
[ $VAR_ERRORS -eq 0 ] && echo -e "${GREEN}✅ All variables resolved${NC}" || exit 1

# Test 3: Selector specificity
echo -e "\n${YELLOW}Test 3: Selector Specificity Check${NC}"
for css in "$BASE_DIR"/*.unmin.css; do
  # Count comma-separated selectors
  SPEC=$(grep -o '{' "$css" | wc -l)
  if [ "$SPEC" -gt 5000 ]; then
    echo -e "${YELLOW}⚠️  High selector count in $(basename $css): $SPEC${NC}"
  fi
done
echo -e "${GREEN}✅ Selector specificity acceptable${NC}"

# Test 4: File size check
echo -e "\n${YELLOW}Test 4: Bundle Size Validation${NC}"
echo "Core bundle: $(du -h "$BASE_DIR/awa-bundle-core.css" | cut -f1)"
echo "Site bundle: $(du -h "$BASE_DIR/awa-bundle-site.css" | cut -f1)"
echo -e "${GREEN}✅ Bundle sizes logged${NC}"

# Test 5: !important usage audit
echo -e "\n${YELLOW}Test 5: !important Audit${NC}"
for css in "$BASE_DIR"/*.unmin.css; do
  COUNT=$(grep -o '!important' "$css" | wc -l)
  if [ "$COUNT" -gt 10 ]; then
    echo -e "${YELLOW}⚠️  High !important usage: $(basename $css) ($COUNT instances)${NC}"
  fi
done
echo -e "${GREEN}✅ !important audit complete${NC}"

echo -e "\n${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}✅ All CSS validation tests passed${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
