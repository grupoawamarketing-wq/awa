---
description: "Prompt console universal — qualquer tarefa no projeto AWA/Magento (copiar, preencher, colar)"
applyTo: "**"
---

# Console Prompt — Universal

Copie, preencha `[...]`, cole no agente.

```
┌──────────────────────────────────────────────────────────────┐
│ AWA · MAGENTO 2.4.8 · awamotos.com                           │
└──────────────────────────────────────────────────────────────┘

> TAREFA
  Tipo:     [bug | feature | refactor | audit | deploy | investigar]
  Objetivo: [1 frase — o que deve estar pronto ao final]
  Escopo:   [arquivos/módulos/páginas envolvidos]
  Fora:     [o que NÃO mexer]

> CONTEXTO (opcional)
  URL/página:  [...]
  Erro/log:    [...]
  Evidência:   [screenshot, stack trace, curl, print]

> REGRAS
  • Executar — não especular; ler código antes de editar
  • Fix mínimo — sem refatorar fora do escopo
  • Magento: DI via construtor, sem ObjectManager, sem core/vendor
  • Frontend: tema AWA_Custom/ayo_home5_child, tokens var(--awa-*)
  • Validar: php -l · logs · cache se necessário

> FLUXO
  1. Entender → ler arquivos relevantes + reproduzir o problema
  2. Causa raiz → identificar antes de corrigir
  3. Implementar → diff pequeno, padrão do projeto
  4. Validar → teste/comando que prova que funcionou
  5. Reportar → o que mudou, por quê, como verificar

> ENTREGA
  [ ] Causa raiz explicada
  [ ] Arquivos alterados listados
  [ ] Comandos de validação executados
  [ ] Sem regressão óbvia nas áreas adjacentes
```

---

## Atalhos por tipo

### Bug
```
> TAREFA
  Tipo: bug
  Objetivo: Corrigir [sintoma] em [onde]
  Escopo: [módulo/arquivo]
> CONTEXTO
  Erro/log: tail var/log/exception.log
> FLUXO + validar logs limpos após fix
```

### Visual / layout
```
> TAREFA
  Tipo: bug
  Objetivo: Corrigir layout [elemento] em [URL] viewport [390|1366]
  Escopo: awa-bundle-[core|category|site|refinements].unmin.css ou PHTML tema filho
> FLUXO
  reproduzir → grep seletor → fix mínimo → deploy tema → Playwright Docker:
  /opt/playwright-job/sync.sh && /opt/playwright-job/run.sh test [spec] --workers=1
```

### Feature / módulo
```
> TAREFA
  Tipo: feature
  Objetivo: [comportamento novo]
  Escopo: app/code/GrupoAwamotos/[Modulo]/
> FLUXO
  ler module.xml/di.xml → implementar → setup:upgrade + cache:flush
```

### Audit / investigar
```
> TAREFA
  Tipo: audit
  Objetivo: Mapear [problema] e propor fix — sem editar ainda
  Escopo: [área]
> ENTREGA: relatório com causa, risco, fix sugerido, arquivos:linhas
```

### Deploy / ops
```
> TAREFA
  Tipo: deploy
  Objetivo: Aplicar [mudança] em produção com rollback claro
> FLUXO
  backup estado → executar → cache/FPC Redis DB2 → smoke test
```

---

## One-liner (mínimo)

```
[Tipo] · Objetivo: [X] · Escopo: [Y] · Não mexer: [Z].
Leia antes de editar. Fix mínimo. Valide e reporte causa + arquivos + como testar.
```
