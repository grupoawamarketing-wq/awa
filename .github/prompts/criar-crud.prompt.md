---
description: "Cria um CRUD completo no Magento 2 — db_schema, Model, Repository, API, Controller, Block, Grid Admin"
agent: "Awa"
tools:
  - codebase
  - edit
  - execute
  - changes
  - problems

---

Crie um CRUD completo no Magento 2 para a entidade especificada.

## O que criar:

### 1. Banco de Dados
- `etc/db_schema.xml` com tabela, colunas, índices, PKs
- Gerar whitelist: `php bin/magento setup:db-declaration:generate-whitelist`

### 2. API / Service Contracts (Api/)
- `Api/Data/EntityInterface.php` — getters/setters da entidade
- `Api/EntityRepositoryInterface.php` — save, getById, delete, getList
- `Api/Data/EntitySearchResultsInterface.php` — para paginação

### 3. Model
- `Model/Entity.php` — implementa DataInterface
- `Model/ResourceModel/Entity.php` — Resource Model
- `Model/ResourceModel/Entity/Collection.php` — Collection
- `Model/EntityRepository.php` — implementa RepositoryInterface

### 4. Controller Admin
- `Controller/Adminhtml/Entity/Index.php` — listagem (grid)
- `Controller/Adminhtml/Entity/Edit.php` — formulário
- `Controller/Adminhtml/Entity/Save.php` — salvar
- `Controller/Adminhtml/Entity/Delete.php` — deletar
- `Controller/Adminhtml/Entity/NewAction.php` — novo

### 5. Admin UI
- `view/adminhtml/layout/` — XMLs de layout
- `view/adminhtml/ui_component/` — UI Component Grid + Form
- Menu em `etc/adminhtml/menu.xml`
- ACL em `etc/acl.xml`
- Rotas em `etc/adminhtml/routes.xml`

### 6. DI e Config
- `etc/di.xml` — preferências (interface → implementação)
- `etc/module.xml` — se módulo novo, com dependências
- `registration.php` — se módulo novo

### 7. Validação
- `php -l` em todos os arquivos criados
- `php bin/magento setup:upgrade`
- `php bin/magento cache:clean`

## Regras:
- Paginação obrigatória (SearchCriteria)
- `declare(strict_types=1)` em todos os arquivos
- DI via construtor (NUNCA ObjectManager)
- ACL para controle de acesso admin
- Validação de input no Controller Save
