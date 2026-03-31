---
description: "Refatora código Magento 2 — melhora legibilidade, DI, tipagem, performance"
mode: agent
tools:
  - codebase
  - terminal
  - problems
  - usages
---

Refatore o código Magento 2 especificado mantendo a mesma funcionalidade.

## Workflow:

1. **Leia o código** — Entenda completamente o que ele faz
2. **Verifique DI** — Leia `di.xml` e `module.xml` do módulo
3. **Identifique usos** — Use `#usages` ou `grep` para ver onde é usado
4. **Verifique sintaxe ANTES** — `php -l` nos arquivos
5. **Refatore** — Aplique as melhorias
6. **Verifique DEPOIS** — `php -l`, cache clean, verificar logs

## Melhorias em ordem de prioridade:
1. Substituir ObjectManager por DI via construtor
2. Adicionar `declare(strict_types=1)` onde falta
3. Adicionar type hints em parâmetros e retornos
4. Extrair lógica duplicada para Services/Helpers
5. Simplificar condicionais complexas
6. Melhorar error handling (catches vazios)
7. Substituir queries SQL diretas por Repository/Collection
8. Remover `var_dump`, `print_r`, `echo` debug
9. Remover código morto
10. Otimizar Collections (select apenas colunas necessárias)

## Regras:
- NUNCA mude o comportamento externo
- NUNCA refatore código que não foi pedido
- NUNCA altere arquivos do core/vendor
- Mantenha compatibilidade com `di.xml`
- Se renomear classes, atualize TODOS os XMLs referentes
- Limpe cache após mudanças
