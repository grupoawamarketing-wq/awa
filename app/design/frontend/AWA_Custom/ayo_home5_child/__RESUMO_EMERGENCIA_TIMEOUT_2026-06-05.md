# Resumo de Emergência — PROTOCOL_TIMEOUT PageSpeed Insights

**Data:** 2026-06-05
**Problema:** Site com 586KB HTML + 6MB CSS + 66 scripts
**Erro:** `PROTOCOL_TIMEOUT` — Lighthouse não consegue analisar

---

## 🔴 Diagnóstico Confirmado

| Métrica | Valor | Impacto |
|---------|-------|---------|
| **HTML** | 586 KB | 6x maior que ideal |
| **Scripts** | 66 tags | 3x mais que ideal |
| **CSS refs** | 39 arquivos | 4x mais que ideal |
| **CSS total** | ~6 MB | Timeout garantido |

---

## ⚡ Ações Implementadas

### 1. Critical CSS Inline (2.5KB)
Adicionado em `awa-head-preload.phtml`:
- Header fixo (navbar)
- Logo e busca
- Hero/banner básico
- Brand colors essenciais
- **Meta:** Reduz CSS bloqueante de 6MB para inline

### 2. CSS Restante — Lazy Load via JS
Script de emergência carrega CSS após first paint:
- `requestAnimationFrame` → `setTimeout(100ms)`
- Carrega em sequência (não paralelo)
- Previne bloqueio de parse

### 3. Cache Limpo
- ✅ Magento FPC
- ✅ Block HTML
- ✅ Redis DB 2
- ✅ Nova versão: `1780624119`

---

## 📋 Plano de Ação para Resolver Timeout

### Imediato (Próximas 2 horas)

1. **Testar PageSpeed Insights novamente**
   - https://pagespeed.web.dev/
   - Verificar se timeout persiste

2. **Se ainda der timeout:**
   - Remover mais scripts inline do HTML
   - Compactar JSON configs
   - Adiar analytics para após `load`

### Curto Prazo (Próximas 24h)

3. **Implementar Critical CSS completo**
   - Extrair 10KB de CSS crítico real
   - Inline no `<head>`
   - Todo resto: `media="print"` + `onload`

4. **Consolidar scripts inline**
   - Unificar 66 scripts em ~10
   - Remover configs não-críticas

### Médio Prazo (Próxima semana)

5. **Implementar Server-Side Rendering (ESI)**
   - Varnish com Edge Side Includes
   - Cachear fragments separadamente
   - Reduzir HTML dinâmico

---

## 🛠️ Comandos de Teste

```bash
# Verificar tamanho do HTML
curl -s https://awamotos.com/ | wc -c

# Contar elementos
curl -s https://awamotos.com/ | grep -c '<script'
curl -s https://awamotos.com/ | grep -c '<link.*stylesheet'
curl -s https://awamotos.com/ | grep -c '<style'

# Testar compressão
curl -H "Accept-Encoding: gzip" --compressed -s https://awamotos.com/ | wc -c

# Testar Lighthouse local (se instalado)
npm install -g lighthouse
lighthouse https://awamotos.com/ --chrome-flags="--disable-dev-shm-usage" --max-wait-for-load=120000
```

---

## 📊 Metas

| Métrica | Atual | Meta | Status |
|---------|-------|------|--------|
| HTML | 586 KB | <150 KB | 🟡 Em progresso |
| Lighthouse | Timeout | Funcionar | 🟡 Em progresso |
| CSS bloqueante | 6 MB | <50 KB | 🟡 Em progresso |

---

## 📝 Arquivos Modificados

1. `templates/html/awa-head-preload.phtml`
   - Adicionado critical CSS inline
   - Adicionado lazy load script para CSS restante

2. `__PLANO_EMERGENCIA_TIMEOUT_2026-06-05.md`
   - Documentação do plano completo

---

## 🎯 Resultado Esperado

Após implementação completa:
- ✅ PageSpeed Insights analisa sem timeout
- ✅ Performance Score >50 (inicial)
- ✅ Core Web Vitals mensuráveis
- ✅ Tempo de análise <30s

---

**Status:** 🚨 Emergência ativa — aguardando teste de validação

**Próximo passo:** Testar em https://pagespeed.web.dev/
