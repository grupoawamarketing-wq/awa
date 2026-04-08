---
description: "Cria um módulo Magento 2 completo sob o namespace GrupoAwamotos — registration.php, module.xml, di.xml, estrutura de pastas e classe inicial"
agent: "agent"
tools:
  - codebase
  - edit
  - execute
  - changes
  - problems
---

Crie um novo módulo Magento 2 completo sob o namespace `GrupoAwamotos`.

## Variáveis

- **Nome do módulo:** `$NOME_MODULO` (ex: `NotificacaoSms`)
- **Dependências principais:** `$DEPENDENCIAS` (ex: `Magento_Sales, GrupoAwamotos_B2B`)
- **Tipo de módulo:** `$TIPO` (pode ser: feature, fix, integration, ui)

## O que criar

### 1. registration.php
```php
<?php
declare(strict_types=1);
use Magento\Framework\Component\ComponentRegistrar;
ComponentRegistrar::register(ComponentRegistrar::MODULE, 'GrupoAwamotos_$NOME_MODULO', __DIR__);
```

### 2. etc/module.xml
- Incluir `<sequence>` com as dependências informadas
- Versão inicial: `102.0.0` (padrão AWA)

### 3. etc/di.xml
- Estrutura mínima pronta para preferences e plugins

### 4. Estrutura de pastas completa
```
app/code/GrupoAwamotos/$NOME_MODULO/
├── registration.php
├── etc/
│   ├── module.xml
│   └── di.xml
```
Se o tipo for `feature`, adicionar também:
- `Api/` + `Api/Data/` — service contracts
- `Model/` — implementações
- `Controller/Adminhtml/` e `etc/adminhtml/routes.xml` — se tiver UI admin
- `view/frontend/layout/` e `view/frontend/templates/` — se tiver frontend

Se o tipo for `integration`:
- `Cron/` — jobs de sync
- `Model/Sync/` — lógica de integração
- `etc/crontab.xml`

Se o tipo for `fix` (bugfix/patch):
- `Plugin/` — interceptors
- `Observer/` + `etc/events.xml`

### 5. Classe principal inicial
Crie a primeira classe real do módulo (Service, Observer, Plugin ou Cron) com:
- `declare(strict_types=1)`
- DI via construtor com `LoggerInterface`
- Método principal com try/catch e log de erro
- **Zero placeholders** — lógica mínima funcional

## Validação obrigatória após criação

```bash
# Sintaxe PHP em todos os arquivos
find app/code/GrupoAwamotos/$NOME_MODULO -name "*.php" -exec php -l {} \;

# Registrar o módulo
php bin/magento module:enable GrupoAwamotos_$NOME_MODULO
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:clean
```

## Regras
- NUNCA use ObjectManager — DI via construtor obrigatório
- SEMPRE `declare(strict_types=1)` em todo .php
- Versão no module.xml: `102.0.0` (padrão AWA)
- PSR-12 em todo código
- DocBlocks com `@param`, `@return`, `@throws`
- Verifique se já existe módulo com nome semelhante antes de criar
