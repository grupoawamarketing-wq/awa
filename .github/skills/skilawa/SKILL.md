---
name: skilawa
description: "Skill especializada em módulos AWA Motos (GrupoAwamotos) para Magento 2. Use quando: criar novo módulo Magento, editar módulo existente, debugar módulo customizado, integrar com ERP via SQL Server, configurar sistema B2B (aprovação CNPJ, grupos Atacado/VIP/Revendedor, cotações), implementar fitment de peças por modelo de moto, customizar tema Ayo/Rokanthemes, criar Observer/Plugin/Cron, implementar CLI command, criar CRUD admin, criar Repository/Model/Api, configurar db_schema.xml, resolver erros de DI compile, ou qualquer tarefa que envolva app/code/GrupoAwamotos."
---

# AWA Motos — Skill de Desenvolvimento Magento 2

## Contexto de Negócio
- **Empresa:** AWA Motos — distribuidora de peças para motos (Araraquara, SP)
- **Produtos:** Bagageiros, baús, retrovisores, acessórios para motos
- **Motos foco:** Honda CG 160, Titan, Fan, Bros 160, XRE 300, CB 300, Yamaha Fazer 250, Factor 150
- **Stack:** Magento 2.4.8-p3, PHP 8.4, MySQL, Redis, Nginx, Elasticsearch
- **Namespace principal:** `GrupoAwamotos`

## Módulos Customizados — Referência Rápida

### Módulos Core (sempre verificar dependências)
| Módulo | Função | Arquivos-chave |
|---|---|---|
| `BrazilCustomer` | CPF, CNPJ, PF/PJ, RG, IE | `Setup/Patch/`, `Plugin/` |
| `B2B` | Grupos Atacado/VIP/Revendedor, aprovação, cotações | `Model/`, `Observer/` |
| `ERPIntegration` | Sync SQL Server (estoque, catálogo, pedidos) | `Cron/`, `Model/Sync/` |
| `Fitment` | Compatibilidade peça x moto | `Model/`, `Block/` |

### Módulos de Vendas/Marketing
| Módulo | Função |
|---|---|
| `AbandonedCart` | Recuperação multi-onda (e-mail + cupons) |
| `SalesIntelligence` | Dashboard + previsão de demanda |
| `SmartSuggestions` | Recompra via análise RFM + WhatsApp |
| `SocialProof` | Visualizações do dia, mais vendido 30d |
| `SchemaOrg` | JSON-LD e Open Graph para SEO |

### Módulos de Infraestrutura
| Módulo | Função |
|---|---|
| `CatalogFix` | Fixes para bugs do Magento 2.4.x |
| `CspFix` | Escrita atômica sri-hashes.json |
| `SmtpFix` | Fix SMTP + Symfony Mailer |
| `LayoutFix` | Fix layout admin |
| `MaintenanceMode` | Whitelist IP + código secreto |
| `StoreSetup` | CLI setup automático |

## Padrões AWA — Criar Novo Módulo

### 1. Estrutura mínima obrigatória
```
app/code/GrupoAwamotos/NomeModulo/
├── registration.php
├── etc/
│   ├── module.xml          # sequence de dependências
│   └── di.xml              # preferences e plugins
├── Api/
│   └── Data/               # Data interfaces (DTOs)
└── Model/
```

### 2. registration.php (template)
```php
<?php
declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'GrupoAwamotos_NomeModulo',
    __DIR__
);
```

### 3. module.xml (template)
```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="GrupoAwamotos_NomeModulo">
        <sequence>
            <module name="Magento_Catalog"/>
        </sequence>
    </module>
</config>
```

### 4. Classe PHP padrão AWA
```php
<?php
declare(strict_types=1);

namespace GrupoAwamotos\NomeModulo\Model;

use Psr\Log\LoggerInterface;

class NomeService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(): void
    {
        try {
            // lógica real aqui
        } catch (\Exception $e) {
            $this->logger->error('Erro em NomeService', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
```

## Tema Ayo (Rokanthemes) — Regras de Customização

- **NUNCA** editar `app/code/Rokanthemes/*` diretamente
- **SEMPRE** sobrescrever no tema: `app/design/frontend/ayo/ayo_default/`
- Templates-chave: `header.phtml`, `footer.phtml`, `header/logo.phtml`
- Blocos CMS: `footer_*`, `rokanthemes_custom_menu*`, `fixed_right`
- Slider CMS: `{{block class="Rokanthemes\SlideBanner\Block\Slider" slider_id="homepageslider"}}`
- Para submenu mobile, adicionar: `<div class="open-children-toggle"></div>`

## Integração ERP — Cuidados

- Módulo: `GrupoAwamotos_ERPIntegration`
- Conexão: SQL Server via `sqlsrv` extension
- Sync: Cron jobs para estoque, catálogo, pedidos
- **NUNCA** alterar dados direto no ERP — apenas leitura
- **SEMPRE** logar cada sync com resultado e tempo

## B2B — Fluxo de Aprovação

1. Cliente cadastra com CNPJ (`BrazilCustomer`)
2. Admin aprova e vincula grupo B2B (`B2B`)
3. Preços especiais via Customer Group Price
4. Pagamento "A Combinar" (`OfflinePayment`) disponível
5. Cotações via módulo `B2B`

## Comandos Frequentes
```bash
# Cache
php bin/magento cache:clean && php bin/magento cache:flush

# Deploy estáticos
php bin/magento setup:static-content:deploy pt_BR -f

# Compile DI
php bin/magento setup:di:compile

# Reindex
php bin/magento indexer:reindex

# Logs
tail -50 var/log/system.log
tail -50 var/log/exception.log

# Validar PHP
php -l arquivo.php
```

## Checklist Pré-Deploy
- [ ] `php -l` em todos os arquivos editados
- [ ] `var/log/system.log` sem erros novos
- [ ] Cache limpo
- [ ] Testar frontend e admin
- [ ] Verificar se indexadores estão OK
