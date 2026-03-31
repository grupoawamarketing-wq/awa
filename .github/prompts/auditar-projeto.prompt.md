---
description: "Faz auditoria completa do projeto Magento 2 — módulos, segurança, performance, configuração"
agent: "agent"
tools:
  - codebase
  - edit
  - execute
  - changes
  - problems

---

Faça uma auditoria completa deste projeto Magento 2.

## Verificações:

### 1. Módulos Customizados
- Listar módulos em `app/code/GrupoAwamotos/`
- Verificar `registration.php`, `module.xml`, `di.xml` de cada um
- Identificar dependências circulares ou conflitos
- Verificar se todos estão habilitados: `php bin/magento module:status`

### 2. Segurança
- Buscar ObjectManager direto: `grep -r 'ObjectManager::getInstance' app/code/`
- Buscar secrets hardcoded: `grep -rn 'password\|api_key\|secret' app/code/`
- Verificar escape de output em PHPTMLs
- Verificar CSRF tokens em formulários
- Buscar SQL direto: `grep -rn 'query(' app/code/`

### 3. Código PHP
- Buscar `var_dump`, `print_r`, `echo` em produção
- Buscar `TODO`, `FIXME`, `HACK`
- Buscar catches vazios: `catch` sem log
- Verificar `declare(strict_types=1)` em arquivos PHP
- Verificar type hints em parâmetros e retornos

### 4. Configuração
- Verificar deploy mode: `php bin/magento deploy:mode:show`
- Verificar cache habilitado: `php bin/magento cache:status`
- Verificar indexadores: `php bin/magento indexer:status`
- Verificar cron: `crontab -l`

### 5. Logs
- Verificar erros recentes: `tail -50 var/log/system.log`
- Verificar exceções: `tail -50 var/log/exception.log`
- Contagem de erros: `wc -l var/log/*.log`

### 6. Performance
- Verificar configuração de cache (Redis)
- Verificar JS/CSS minification
- Verificar Flat Tables
- Verificar índices do banco de dados

## Output:
```
📊 RELATÓRIO DE AUDITORIA MAGENTO 2
====================================
✅ OK: [itens corretos]
⚠️ ATENÇÃO: [itens que precisam de melhoria]
🔴 CRÍTICO: [itens que precisam de correção urgente]

📋 PLANO DE AÇÃO (priorizado):
1. ...
2. ...
```
