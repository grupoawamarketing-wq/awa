---
description: "Implementa um comando CLI para o Magento 2 (bin/magento namespace:verbo) dentro de um módulo GrupoAwamotos existente"
agent: "agent"
tools:
  - codebase
  - edit
  - execute
  - problems
---

Implemente um comando CLI Magento 2 (`bin/magento`) no módulo especificado.

## Variáveis

- **Módulo:** `$MODULO` (ex: `GrupoAwamotos_ERPIntegration`)
- **Comando:** `$COMANDO` (ex: `erp:sync:estoque`)
- **Descrição:** `$DESCRICAO` (ex: "Sincroniza estoque do ERP com Magento")
- **Argumentos/opções:** `$ARGS` (ex: `--dry-run`, `--sku=SKU123`)

## O que implementar

### 1. Classe Command
`Console/Command/$NomeCommand.php`

```php
<?php
declare(strict_types=1);

namespace GrupoAwamotos\$Modulo\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;

class $NomeCommand extends Command
{
    public const COMMAND_NAME = '$comando';

    public function __construct(
        private readonly /* serviço principal */,
        private readonly LoggerInterface $logger,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('$descricao')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simula sem persistir');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isDryRun = (bool) $input->getOption('dry-run');
        $output->writeln('<info>Iniciando $descricao...</info>');

        try {
            // lógica real aqui
            $output->writeln('<info>Concluído.</info>');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('Erro em $NomeCommand', ['error' => $e->getMessage()]);
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
```

### 2. Registrar no di.xml
```xml
<type name="Magento\Framework\Console\CommandList">
    <arguments>
        <argument name="commands" xsi:type="array">
            <item name="grupoawamotos_$modulo_$verbo" xsi:type="object">
                GrupoAwamotos\$Modulo\Console\Command\$NomeCommand
            </item>
        </argument>
    </arguments>
</type>
```

### 3. Injetar o serviço de negócio
- Identifique ou crie o Service/Model que executa a lógica real
- NUNCA coloque lógica de negócio na classe Command
- O Command é apenas I/O — delega para um Service

## Validação obrigatória

```bash
php -l app/code/GrupoAwamotos/$Modulo/Console/Command/$NomeCommand.php
php bin/magento setup:di:compile
php bin/magento cache:clean

# Verificar registro
php bin/magento list | grep "$namespace"

# Testar dry-run
php bin/magento $comando --dry-run
```

## Regras
- Command é APENAS I/O — toda lógica fica em Model/Service
- Use `Command::SUCCESS` e `Command::FAILURE` (não 0/1 hardcoded)
- Sempre ofereça `--dry-run` para commands de sync/escrita
- Sempre exiba progresso com `$output->writeln`
- Log erros com `$this->logger->error()` antes de retornar FAILURE
- Adicione ao `di.xml` do módulo — não criar novo di.xml
