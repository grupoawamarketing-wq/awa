
---

## Ciclo 5 — Fixes de Dashboard e Nav (2026-04-10)

### Screenshots analisados
| Screenshot | Bug | Status |
|---|---|---|
| Print 1 | PDP: preço Faça login para usuário logado | ✅ Fix JS via  (Ciclo 4) |
| Print 2 | Dashboard:  sobrepoõe Histórico de Compras | ✅ C5-1: z-index isolation no  |
| Print 3 | Conta: nav lateral como links vermelhos simples | ✅ C5-2: seletor longo com  em  |

### C5-1 — Dashboard card z-index
-  — contexto de empilhamento
-  — base
-  — card ativo acima dos irmãos

### C5-2 — Account sidebar nav override
- Seletor longo () ganha especificidade sobre o tema pai
- Links não-ativos:  (neutro)
- Hover:  + border-left vermelho claro
- Ativo/current:  + border-left sólido

**Deploy:**  1,096,133 bytes — Apr 11 03:22


---

## Ciclo 5 — Fixes de Dashboard e Nav (2026-04-10)

### Screenshots analisados
| Screenshot | Bug | Status |
|---|---|---|
| Print 1 | PDP: preco Faca login para usuario logado | FIXED via awa-b2b-pdp-price-reload.js (Ciclo 4) |
| Print 2 | Dashboard: .quotes-card sobrepooe Historico de Compras | FIXED C5-1: z-index isolation no .b2b-summary-cards |
| Print 3 | Conta: nav lateral como links vermelhos simples | FIXED C5-2: seletor longo com !important em _awa-cycle1-fixes.less |

### C5-1 — Dashboard card z-index
- .b2b-summary-cards position:relative + isolation:isolate — contexto de empilhamento
- .summary-card position:relative + z-index:1 — baseline
- .summary-card:hover, :focus-within z-index:10 — card ativo acima dos irmaos

### C5-2 — Account sidebar nav override
- Seletor longo (.content .nav.items > .nav.item > a) ganha especificidade sobre o tema pai
- Links nao-ativos: color:#4b5563 !important (neutro)
- Hover: color:var(--awa-primary) + border-left vermelho claro
- Ativo/current: color:var(--awa-primary) + border-left solido

**Deploy:** styles-l.css 1,096,133 bytes — Apr 11 03:22
