# Template De Modulo Magento

## Objetivo
Padronizar a criacao de modulos para reduzir acoplamento, facilitar manutencao e manter consistencia com praticas Magento.

## Estrutura Minima
```txt
app/code/Vendor/Modulo/
├── registration.php
├── etc/
│   ├── module.xml
│   ├── di.xml
│   ├── events.xml
│   ├── db_schema.xml
│   └── frontend/
│       └── routes.xml
├── Api/
│   └── Data/
├── Model/
├── view/
│   └── frontend/
│       ├── layout/
│       ├── templates/
│       └── web/
└── composer.json
```

## Regras De Implementacao
- `declare(strict_types=1)` em novos arquivos PHP
- sem `ObjectManager` direto
- DI via construtor
- service contracts em `Api/` para expor servicos reutilizaveis
- repositories para acesso a entidades quando aplicavel
- evite `Helper` como deposito de logica generica

## Checklist Ao Criar Um Modulo
- [ ] `registration.php` e `etc/module.xml` presentes
- [ ] `etc/di.xml` apenas quando necessario
- [ ] `etc/db_schema.xml` quando houver persistencia
- [ ] `etc/webapi.xml` quando houver API publica
- [ ] `etc/adminhtml/system.xml` quando houver configuracao
- [ ] `view/frontend/layout` organizado por handle
- [ ] `view/frontend/web` com nomes `kebab-case`

## Padrao Para Service Contract
- `Api/FooRepositoryInterface.php`
- `Api/Data/FooInterface.php`
- `Model/FooRepository.php`
- `Model/Data/Foo.php`

## Padrao Para Layout E Template
- layout XML deve ser pequeno e previsivel
- templates devem escapar output quando aplicavel
- JS deve inicializar via `x-magento-init` ou `data-mage-init`

## Validacoes Minimas
```bash
composer quality:php-syntax
composer quality:governance
phpcs --standard=phpcs.xml app/code/Vendor/Modulo
```
