---
applyTo: "**/Observer/**/*.php,**/Plugin/**/*.php,**/Cron/**/*.php,**/etc/events.xml,**/etc/crontab.xml"
---

# Regras para Observers, Plugins e Cron (Magento 2)

## Observers

### Classe
```php
<?php
declare(strict_types=1);

namespace GrupoAwamotos\ModuleName\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class EntityActionObserver implements ObserverInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    public function execute(Observer $observer): void
    {
        $entity = $observer->getEvent()->getData('entity');
        if (!$entity) {
            return;
        }

        try {
            // lógica do observer
        } catch (\Exception $e) {
            $this->logger->error('Observer error: ' . $e->getMessage());
        }
    }
}
```

### Configuração (events.xml)
```xml
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Event/etc/events.xsd">
    <event name="sales_order_place_after">
        <observer name="grupoawamotos_module_action_observer"
                  instance="GrupoAwamotos\ModuleName\Observer\EntityActionObserver"/>
    </event>
</config>
```

### Regras de Observer
- SEMPRE implementar `ObserverInterface`
- Assinatura: `public function execute(Observer $observer): void`
- Nome do observer em events.xml: `grupoawamotos_module_descricao` (snake_case)
- Validar dados do evento antes de usar (null check)
- Escopo: colocar events.xml em `etc/`, `etc/frontend/` ou `etc/adminhtml/` conforme necessidade
- NUNCA lançar exceção que interrompa o fluxo principal — logar e continuar

---

## Plugins (Interceptors)

### Tipos de Plugin
```php
// Before — modifica argumentos ANTES da execução
public function beforeSetName(TargetClass $subject, string $name): array
{
    return [trim($name)]; // retorna array de argumentos modificados
}

// After — modifica retorno DEPOIS da execução
public function afterGetName(TargetClass $subject, string $result): string
{
    return strtoupper($result);
}

// Around — controla execução completa (usar com cautela)
public function aroundGetName(TargetClass $subject, callable $proceed): string
{
    // antes
    $result = $proceed(); // chamar original
    // depois
    return $result;
}
```

### Configuração (di.xml)
```xml
<type name="Magento\Catalog\Model\Product">
    <plugin name="grupoawamotos_module_product_plugin"
            type="GrupoAwamotos\ModuleName\Plugin\ProductPlugin"
            sortOrder="10"/>
</type>
```

### Regras de Plugin
- Preferir `after` sobre `around` (menor impacto)
- `around` SEMPRE chamar `$proceed()` (exceto se intencionalmente bloqueando)
- `before` retorna `array` com argumentos ou `null` para não modificar
- `after` recebe `$result` como segundo parâmetro — retornar o resultado (modificado ou não)
- Sessions no construtor: usar `Proxy` (ex: `Magento\Customer\Model\Session\Proxy`)
- `sortOrder` define precedência — menor = executa primeiro
- Escopo: `etc/di.xml` (global), `etc/frontend/di.xml`, `etc/adminhtml/di.xml`

---

## Cron Jobs

### Classe
```php
<?php
declare(strict_types=1);

namespace GrupoAwamotos\ModuleName\Cron;

use Psr\Log\LoggerInterface;

class ProcessEntities
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly LoggerInterface $logger
    ) {}

    public function execute(): void
    {
        try {
            $collection = $this->collectionFactory->create();
            $collection->addFieldToFilter('status', 'pending');

            foreach ($collection as $entity) {
                try {
                    // processar item individualmente
                } catch (\Exception $e) {
                    $this->logger->error('Cron item error', [
                        'entity_id' => $entity->getId(),
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } catch (\Exception $e) {
            $this->logger->critical('Cron job failed: ' . $e->getMessage());
        }
    }
}
```

### Configuração (crontab.xml)
```xml
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Cron:etc/crontab.xsd">
    <group id="default">
        <job name="grupoawamotos_module_process_entities"
             instance="GrupoAwamotos\ModuleName\Cron\ProcessEntities"
             method="execute">
            <schedule>0 2 * * *</schedule>
        </job>
    </group>
</config>
```

### Regras de Cron
- Método `execute(): void` sem parâmetros
- Nome do job: `grupoawamotos_module_descricao` (snake_case)
- Group `default` para jobs normais
- Try/catch duplo: externo para falha total, interno para cada item
- NUNCA executar sem limite — usar paginação ou LIMIT em collections
- Logar início/fim para rastreabilidade em jobs longos
- Verificar `config->isEnabled()` antes de executar se o módulo tem toggle

## NUNCA
- Observer que lança exceção sem tratamento (pode quebrar checkout, etc.)
- Plugin `around` sem chamar `$proceed()`
- Cron sem logging de erro
- Session direta no construtor de Plugin (usar Proxy)
- Observer para lógica que deveria ser Plugin (e vice-versa)
