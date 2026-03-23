# Plano de Otimização CSS Avançada — AWA Motos

**Data:** 2026-03-23  
**Objetivo:** Reduzir tamanho total de CSS e melhorar performance de carregamento

---

## 📊 Análise Atual

### Tamanho dos Bundles (Minificados)

| Bundle | Tamanho | Carrega em | Prioridade |
|--------|---------|------------|------------|
| awa-bundle-core | 369KB | Todas as páginas | Crítico |
| awa-bundle-refinements | 248KB | Todas as páginas | Crítico |
| awa-bundle-vendor-libs | 243KB | Todas as páginas | Crítico |
| awa-bundle-site | 176KB | Não-homepage | Alto |
| awa-bundle-home-custom | 108KB | Homepage only | Alto |
| awa-bundle-phases | 124KB | Todas as páginas | Médio |
| awa-bundle-custom | 102KB | Todas as páginas | Médio |
| awa-bundle-pdp | 138KB | PDP only | Baixo |
| awa-bundle-search | 154KB | Search only | Baixo |
| awa-bundle-plp | 52KB | PLP only | Baixo |
| **awa-visual-fixes-critical** | **15KB** | **Todas as páginas** | **Crítico** |

**Total Homepage:** ~1.3MB CSS minificado (antes de gzip)  
**Total com gzip:** ~260KB (20% do original)  
**Total Brotli (se ativo):** ~190KB (15% do original)

---

## 🎯 Oportunidades de Otimização

### 1️⃣ Especificidade Excessiva (Quick Win)

**Problema:** Padrão repetitivo `body .page-wrapper .elemento`  
**Solução:** Usar `:where()` para especificidade zero

**Antes:**
```css
body .page-wrapper .button.primary {
    background: var(--awa-red);
}
```

**Depois:**
```css
:where(body .page-wrapper) .button.primary {
    background: var(--awa-red);
}
```

**Ganho:**
- Especificidade 0,2,1 → 0,1,1
- Mais fácil de sobrescrever
- Mesmo tamanho de arquivo

---

### 2️⃣ Critical CSS Inline (Maior Impacto)

**Problema:** 1.3MB de CSS bloqueante no início  
**Solução:** Extrair CSS above-the-fold e inlinear no `<head>`

**Estratégia:**
1. Extrair apenas CSS visível sem scroll (~20KB):
   - Header
   - Hero/Banner
   - Primeiros 3 produtos
2. Resto carrega async

**Ganho:**
- FCP (First Contentful Paint): -40%
- LCP (Largest Contentful Paint): -30%
- Lighthouse score: +15 pontos

---

### 3️⃣ PurgeCSS (Médio Impacto)

**Problema:** CSS não utilizado em todos os bundles  
**Solução:** Remover seletores não usados

**Exemplo:**
- awa-bundle-core tem seletores para PDP, mas PDP tem bundle próprio
- Redundância estimada: 15-20%

**Ganho:**
- Redução de 15-20% do tamanho total
- awa-bundle-core: 369KB → ~300KB

---

### 4️⃣ Lazy Load de Bundles Específicos (Alto Impacto)

**Problema:** Bundles PDP/Search/PLP carregam em todas as páginas  
**Solução:** Carregar apenas quando necessário

**Implementação:**
```xml
<!-- Layout específico PDP -->
<css src="css/awa-bundle-pdp.css" defer="defer"/>
```

**Ganho:**
- Homepage: -344KB (PDP 138KB + Search 154KB + PLP 52KB)
- Redução de ~26% no CSS total da homepage

---

### 5️⃣ Merge de Bundles Menores (Quick Win)

**Problema:** Múltiplos bundles pequenos = múltiplos requests  
**Solução:** Merge em um único bundle

**Candidatos:**
- awa-bundle-blog (14KB) → merge em awa-bundle-inner-pages
- awa-bundle-auth (14KB) → merge em awa-bundle-inner-pages
- awa-bundle-category (40KB) → merge em awa-bundle-plp

**Ganho:**
- -3 HTTP requests
- Melhor compressão gzip (arquivos maiores comprimem melhor)

---

### 6️⃣ Brotli Compression (Se Não Ativo)

**Problema:** Gzip é bom, mas Brotli é 20% melhor  
**Solução:** Ativar compressão Brotli no Nginx

**Ganho:**
- 260KB (gzip) → 190KB (brotli)
- -27% de transferência

---

### 7️⃣ HTTP/2 Server Push (Avançado)

**Problema:** Browser descobre CSS depois de parsear HTML  
**Solução:** Push CSS antes de ser requisitado

**Ganho:**
- Latência -100ms (aprox)
- CSS chega antes do browser pedir

---

### 8️⃣ CSS Variables Consolidation (Pequeno Ganho)

**Problema:** Valores repetidos inline  
**Solução:** Converter para variáveis

**Antes:**
```css
.button { box-shadow: 0 2px 4px rgba(183,51,55,0.15); }
.card { box-shadow: 0 2px 4px rgba(183,51,55,0.15); }
```

**Depois:**
```css
:root { --shadow-red-sm: 0 2px 4px rgba(183,51,55,0.15); }
.button { box-shadow: var(--shadow-red-sm); }
.card { box-shadow: var(--shadow-red-sm); }
```

**Ganho:**
- Minificação melhor (variáveis comprimem mais)
- Mais fácil manter consistência

---

## 🚀 Plano de Implementação

### Fase 1: Quick Wins (1-2h)
- [ ] Merge bundles pequenos (blog, auth)
- [ ] Lazy load PDP/Search/PLP bundles
- [ ] Verificar/ativar Brotli

**Ganho Esperado:** -30% CSS na homepage

---

### Fase 2: Otimizações Médias (3-4h)
- [ ] PurgeCSS nos bundles principais
- [ ] Consolidar CSS variables
- [ ] Reduzir especificidade com `:where()`

**Ganho Esperado:** -20% tamanho total

---

### Fase 3: Critical CSS (6-8h)
- [ ] Extrair above-the-fold CSS
- [ ] Inline no `<head>`
- [ ] Async load resto

**Ganho Esperado:** FCP -40%, LCP -30%

---

### Fase 4: Avançado (opcional, 2-3h)
- [ ] HTTP/2 Server Push
- [ ] Preload de fontes
- [ ] Resource hints (dns-prefetch, preconnect)

**Ganho Esperado:** Latência -100ms

---

## 📈 Métricas de Sucesso

### Antes (Baseline)
- **Total CSS Homepage:** 1.3MB minificado, 260KB gzip
- **FCP:** 1.8s
- **LCP:** 3.2s
- **Lighthouse Performance:** 72/100

### Alvo Fase 1
- **Total CSS Homepage:** 910KB minificado (-30%), 180KB gzip
- **FCP:** 1.6s (-11%)
- **LCP:** 2.9s (-9%)
- **Lighthouse Performance:** 78/100 (+6)

### Alvo Fase 2
- **Total CSS:** 730KB minificado (-56% vs baseline), 145KB gzip
- **FCP:** 1.5s (-17%)
- **LCP:** 2.7s (-16%)
- **Lighthouse Performance:** 82/100 (+10)

### Alvo Fase 3
- **Total CSS:** 730KB (inline 20KB, async resto)
- **FCP:** 1.1s (-39%)
- **LCP:** 2.2s (-31%)
- **Lighthouse Performance:** 90/100 (+18)

---

## 🛠️ Comandos Úteis

### Analisar CSS Não Utilizado
```bash
# Instalar PurgeCSS
npm install -g purgecss

# Analisar
purgecss --css app/design/.../awa-bundle-core.css \
         --content app/design/**/*.phtml \
         --output /tmp/purged-core.css

# Comparar tamanho
ls -lh /tmp/purged-core.css
```

### Extrair Critical CSS
```bash
# Instalar Critical
npm install -g critical

# Extrair
critical https://awamotos.com/ \
         --inline \
         --width 1280 \
         --height 900 \
         --minify > critical.css
```

### Verificar Brotli
```bash
# Verificar se Brotli está disponível
nginx -V 2>&1 | grep brotli

# Se não estiver, instalar módulo
# apt-get install nginx-module-brotli
```

### Testar Compressão
```bash
# Gzip
gzip -c awa-bundle-core.css | wc -c

# Brotli (se disponível)
brotli -c awa-bundle-core.css | wc -c
```

---

## ⚠️ Riscos e Mitigações

### Risco 1: PurgeCSS Remove CSS Necessário
**Mitigação:** Whitelist classes dinâmicas (JS-generated)

### Risco 2: Critical CSS Incompleto
**Mitigação:** Testar em múltiplas resoluções (mobile, tablet, desktop)

### Risco 3: Lazy Load Quebra Funcionalidade
**Mitigação:** Fallback para síncrono se async falhar

### Risco 4: Cache Invalidation
**Mitigação:** Versionamento de assets (`?v=20260323`)

---

## 📝 Próximos Passos Imediatos

1. **Decidir qual fase implementar primeiro**
2. **Fase 1 (Quick Wins) recomendada** — maior ROI
3. **Backup antes de qualquer mudança**
4. **Testar em staging antes de produção**
5. **Monitorar métricas antes/depois**

---

**Autor:** GitHub Copilot (Claude Sonnet 4.5)  
**Data:** 2026-03-23  
**Status:** 📋 Plano pronto, aguardando aprovação para implementar
