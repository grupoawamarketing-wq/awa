---
description: "Diagnostica e corrige bug no Magento 2 — logs, stack trace, causa raiz, fix mínimo"
agent: "Debugger"
tools:
  - codebase
  - edit
  - execute
  - changes
  - problems
  - usages
---
Diagnostique e corrija o bug descrito no Magento 2.
## Workflow OBRIGATÓRIO:

1. **Verifique logs** — `tail -100 var/log/system.log` e `var/log/exception.log`
2. **Leia o stack trace** — Analise do fim para o início
4. **Verifique DI** — Confira `di.xml`, `module.xml`, preferências
5. **Analise dependências** — Use `#usages` para ver quem chama esse código
6. **Identifique a causa raiz** — Não o sintoma, a CAUSA
7. **Corrija** — Aplique o fix MÍNIMO necessário
8. **Valide** — `php -l`, `php bin/magento cache:clean`, verifique logs
9. **Explique** — Descreva o que causou o bug e como foi corrigido

## Técnicas Magento:
- Verificar `generated/` por classes desatualizadas
- Verificar configuração em `app/etc/env.php`
- Verificar se módulo está habilitado: `php bin/magento module:status`
- Verificar se DI precisa recompilar: `php bin/magento setup:di:compile`
- Verificar permissões de arquivos em `var/`, `generated/`, `pub/static/`

## Regras:
- NUNCA aplique fix sem entender a causa raiz
- NUNCA refatore código que não está relacionado ao bug
- NUNCA altere arquivos do core/vendor
- Se for workaround, diga explicitamente
- Corrija o mínimo necessário
- Se o fix pode quebrar outra coisa, avise
