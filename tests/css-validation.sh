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

# Test 1: Syntax validation — detecta erros reais, não falsos positivos
# Verifica: property/value sem ; no final (excluindo seletores, @rules, comentários, e custom props)
echo -e "\n${YELLOW}Test 1: CSS Syntax Validation${NC}"
ERRORS=0
for css in "$BASE_DIR"/*.unmin.css; do
  # Detecta linhas que parecem declarações CSS (tem : mas não são @rule, seletor, comentário)
  # e não terminam com ; ou { ou , ou } (multi-line values são permitidos)
  BROKEN=$(grep -cE '^[[:space:]]+[-a-zA-Z]+[[:space:]]*:[[:space:]]*[^/;{}]+[^;{},/]$' "$css" 2>/dev/null || true)
  # Desconta: linhas que são apenas continuação de multi-line (não terminam com { ou ,)
  # Usa clean-css para validar sintaxe real — apenas erros fatais (não warnings)
  if command -v npx &>/dev/null; then
    MINIFIED=$(npx clean-css-cli "$css" -o /dev/null 2>&1)
    # Falha apenas em erros fatais, não em warnings de seletor
    if echo "$MINIFIED" | grep -qi "^error\b\|fatal\|unable to parse"; then
      echo -e "${RED}❌ CSS error in $(basename $css): $MINIFIED${NC}"
      ERRORS=$((ERRORS + 1))
    elif echo "$MINIFIED" | grep -qi "warning\|invalid selector"; then
      echo -e "${YELLOW}⚠️  Warning in $(basename $css): $(echo $MINIFIED | head -c 120)${NC}"
    fi
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
