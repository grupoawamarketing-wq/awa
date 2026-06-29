# Plano de Emergência — PageSpeed Insights PROTOCOL_TIMEOUT

**Data:** 2026-06-05
**Problema:** Site tão pesado que Lighthouse não consegue analisar
**Erro:** `PROTOCOL_TIMEOUT` — Chrome DevTools Protocol timeout

---

## 🔴 Diagnóstico do Problema

### Métricas Críticas Encontradas

| Métrica | Valor | Limite Saudável | Status |
|---------|-------|-----------------|--------|
| **Tamanho HTML** | 586 KB | <100 KB | 🔴 **6x maior** |
| **Tags `<script>`** | 66 | <20 | 🔴 **3x mais** |
| **Referências CSS** | 39 | <10 | 🔴 **4x mais** |
| **CSS bloqueante** | ~6 MB | <200 KB | 🔴 **30x maior** |
| **Tempo estimado** | >60s | <10s | 🔴 Timeout |

### Por que o PROTOCOL_TIMEOUT ocorre?

O Chrome DevTools Protocol tem um timeout padrão de **30-60 segundos**. Quando o Lighthouse tenta analisar o site:

1. Baixa o HTML (586KB)
2. Descobre 66 scripts e 39 CSS
3. Inicia parse de 6MB+ de CSS
4. Executa JavaScript complexo
5. **Timeout** — não completa em tempo hábil

---

## ⚡ Ações de Emergência (Implementar Agora)

### 1. REDUZIR HTML DA HOME (Prioridade Máxima)

**Problema:** 586KB de HTML puro. Possíveis causas:
- Menu vertical com todas as categorias inline
- JSON configs grandes inline
- Scripts inline excessivos

**Ação Imediata:**

```php
// awa-head-preload.phtml — Remover configs não-críticas do inline
// Mover para carregamento async via fetch/XHR
```

**Remover/configurar como lazy:**
- [ ] Config JSON do autocomplete (80KB+)
- [ ] Config de customer sections (30KB+)
- [ ] Inline scripts de analytics não-críticos

---

### 2. ADIAR CSS NÃO-CRÍTICO (Critical CSS)

**Problema:** 21 arquivos CSS carregando de uma vez.

**Ação:** Implementar critical CSS inline + restante lazy.

**CSS que DEVE ser bloqueante (acima da dobra):**
- Header (~5KB)
- Typography (~2KB)
- Hero/banner (~3KB)
- **Total: ~10KB**

**CSS que pode ser lazy (abaixo da dobra):**
- Carrosséis de produtos
- Footer
- Menu vertical completo
- Categoria thumbnails
- **Economia: ~5MB**

---

### 3. REMOVER SCRIPTS NÃO-ESSENCIAIS DO HEAD

**Problema:** 66 tags `<script>` no `<head>`.

**Ações:**

| Script | Ação | Prioridade |
|--------|------|------------|
| Google Analytics | Mover para `defer` | Alta |
| Facebook Pixel | Mover para `defer` | Alta |
| JSON configs grandes | Carregar via XHR | Alta |
| Scripts inline de analytics | Consolidar em 1 arquivo | Média |
| Scripts de terceiros | `async` ou `defer` | Média |

---

### 4. COMPACTAR E COMPRIMIR

**Verificar se ativado:**

```nginx
# nginx.conf — Deve estar configurado:
gzip on;
gzip_types text/css application/javascript application/json;
gzip_min_length 1000;
gzip_comp_level 6;

# Brotli (se disponível):
brotli on;
brotli_types text/css application/javascript;
```

**Testar compressão:**
```bash
curl -H "Accept-Encoding: gzip" -I https://awamotos.com/
```

---

## 🛠️ Implementação Imediata

### Passo 1: Criar Critical CSS Inline (10KB)

```php
// Em awa-head-preload.phtml, adicionar:
?>
<style id="awa-critical-above-fold">
/* APENAS o essencial para first paint */
:root{--awa-primary:#b73337;--awa-text:#333}
body{margin:0;font-family:Montserrat,sans-serif}
.header-main{height:64px;background:#fff}
/* ... apenas 10KB ... */
</style>
<?php
```

### Passo 2: Adiar Todo CSS Restante

```php
// Todos os outros CSS carregam com:
// media="print" onload="this.media='all'"
// ou via JavaScript após first paint
```

### Passo 3: Adiar Scripts Não-Críticos

```php
// Remover do <head>, adicionar antes de </body>:
<script defer src="analytics.js"></script>
<script defer src="facebook-pixel.js"></script>
```

---

## 📊 Metas de Performance

| Métrica | Atual | Meta | Redução |
|---------|-------|------|---------|
| HTML | 586 KB | <150 KB | -75% |
| CSS bloqueante | ~6 MB | <50 KB | -99% |
| Scripts no head | 66 | <10 | -85% |
| Tempo de análise | Timeout | <15s | Funcional |

---

## ✅ Checklist de Implementação

### Hora 1 (Crítico)
- [ ] Identificar CSS crítico (above-fold)
- [ ] Criar critical CSS inline
- [ ] Configurar restante como lazy

### Hora 2 (Importante)
- [ ] Mover scripts não-críticos para `defer`
- [ ] Remover JSON configs grandes do inline
- [ ] Limpar cache e testar

### Hora 3 (Validação)
- [ ] Testar no PageSpeed Insights
- [ ] Verificar se timeout foi resolvido
- [ ] Medir Core Web Vitals

---

## 🔧 Comandos para Diagnóstico

```bash
# Tamanho do HTML
curl -s https://awamotos.com/ | wc -c

# Contar scripts
curl -s https://awamotos.com/ | grep -c '<script'

# Contar CSS
curl -s https://awamotos.com/ | grep -c '<link.*stylesheet\|<style'

# Tempo de resposta
curl -s -o /dev/null -w "%{time_total}s" https://awamotos.com/

# Testar compressão
curl -H "Accept-Encoding: gzip" --compressed -s https://awamotos.com/ | wc -c
```

---

## 🚨 Se Nada Funcionar

Se após as otimizações o timeout persistir:

1. **Usar Lighthouse CLI local** com timeout maior:
   ```bash
   npm install -g lighthouse
   lighthouse https://awamotos.com/ --chrome-flags="--disable-dev-shm-usage" --max-wait-for-load=90000
   ```

2. **Usar WebPageTest** (mais tolerante a timeouts):
   https://www.webpagetest.org/

3. **Implementar Server-Side Rendering (SSR)** para cache:
   - Usar Varnish com ESI (Edge Side Includes)
   - Cachear fragments separadamente

---

## 📈 Resultado Esperado

Após implementação completa:
- ✅ PageSpeed Insights funciona (sem timeout)
- ✅ Performance Score >50 (inicial)
- ✅ Performance Score >80 (após refinamentos)
- ✅ Core Web Vitals passam (LCP <2.5s)

---

**Criado em:** 2026-06-05
**Status:** 🚨 Plano de Emergência Ativo
