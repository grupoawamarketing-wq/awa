<?php
/**
 * Seed B2B Carriers Command
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use GrupoAwamotos\B2B\Model\CarrierService;

class SeedCarriersCommand extends Command
{
    /**
     * @var CarrierService
     */
    private $carrierService;

    /**
     * @param CarrierService $carrierService
     * @param string|null $name
     */
    public function __construct(
        CarrierService $carrierService,
        ?string $name = null
    ) {
        $this->carrierService = $carrierService;
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('b2b:carriers:seed')
            ->setDescription('Cadastra as transportadoras padrão para B2B Brasil');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Cadastrando transportadoras B2B...</info>');

        try {
            $carriers = $this->carrierService->seedDefaultCarriers();

            $output->writeln('<info>Transportadoras cadastradas com sucesso:</info>');
            foreach ($carriers as $carrier) {
                $output->writeln(sprintf(
                    '  - %s (%s)',
                    $carrier->getName(),
                    $carrier->getCode()
                ));
            }

            if (empty($carriers)) {
                $output->writeln('<comment>Nenhuma nova transportadora foi cadastrada. Todas já existem.</comment>');
            }

            $output->writeln('<info>Concluído!</info>');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Erro: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
