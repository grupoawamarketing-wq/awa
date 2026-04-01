<?php

declare(strict_types=1);

namespace GrupoAwamotos\AbandonedCart\Console\Command;

use GrupoAwamotos\AbandonedCart\Model\ResourceModel\AbandonedCart\CollectionFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class ReportCommand extends Command
{
    private CollectionFactory $collectionFactory;

    public function __construct(CollectionFactory $collectionFactory)
    {
        $this->collectionFactory = $collectionFactory;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('abandonedcart:report')
            ->setDescription('Exibe relatório de carrinhos abandonados')
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Período em dias', '30')
            ->addOption('status', 's', InputOption::VALUE_REQUIRED, 'Filtrar por status (pending, recovered, expired)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getOption('days');
        $statusFilter = $input->getOption('status');

        $output->writeln("<info>Relatório de Carrinhos Abandonados (últimos {$days} dias)</info>");
        $output->writeln('');

        // Estatísticas gerais
        $this->showGeneralStats($output, $days);

        // Estatísticas por email
        $this->showEmailStats($output, $days);

        // Lista recente
        $this->showRecentCarts($output, $days, $statusFilter);

        return Command::SUCCESS;
    }

    private function showGeneralStats(OutputInterface $output, int $days): void
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('created_at', ['gteq' => $cutoff]);

        $total = $collection->getSize();

        $recoveredCollection = $this->collectionFactory->create();
        $recoveredCollection->addFieldToFilter('created_at', ['gteq' => $cutoff])
            ->addFieldToFilter('recovered', 1);
        $recovered = $recoveredCollection->getSize();

        $pendingCollection = $this->collectionFactory->create();
        $pendingCollection->addFieldToFilter('created_at', ['gteq' => $cutoff])
            ->addFieldToFilter('recovered', 0)
            ->addFieldToFilter('status', 'pending');
        $pending = $pendingCollection->getSize();

        $valueCollection = $this->collectionFactory->create();
        $valueCollection->addFieldToFilter('created_at', ['gteq' => $cutoff]);
        $totalValue = 0;
        $recoveredValue = 0;
        foreach ($valueCollection as $item) {
            $totalValue += $item->getCartValue();
            if ($item->isRecovered()) {
                $recoveredValue += $item->getCartValue();
            }
        }

        $recoveryRate = $total > 0 ? round(($recovered / $total) * 100, 1) : 0;

        $output->writeln('<comment>📊 Estatísticas Gerais:</comment>');
        $output->writeln("   Total de carrinhos abandonados: <info>{$total}</info>");
        $output->writeln("   Carrinhos recuperados: <info>{$recovered}</info> ({$recoveryRate}%)");
        $output->writeln("   Carrinhos pendentes: <info>{$pending}</info>");
        $output->writeln("   Valor total abandonado: <info>R$ " . number_format($totalValue, 2, ',', '.') . "</info>");
        $output->writeln("   Valor recuperado: <info>R$ " . number_format($recoveredValue, 2, ',', '.') . "</info>");
        $output->writeln('');
    }

    private function showEmailStats(OutputInterface $output, int $days): void
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $output->writeln('<comment>📧 Estatísticas de Email:</comment>');

        for ($i = 1; $i <= 3; $i++) {
            $collection = $this->collectionFactory->create();
            $collection->addFieldToFilter('created_at', ['gteq' => $cutoff])
                ->addFieldToFilter("email_{$i}_sent", 1);
            $sent = $collection->getSize();

            $output->writeln("   Email {$i} enviados: <info>{$sent}</info>");
        }
        $output->writeln('');
    }

    private function showRecentCarts(OutputInterface $output, int $days, ?string $statusFilter): void
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('created_at', ['gteq' => $cutoff])
            ->setOrder('created_at', 'DESC')
            ->setPageSize(20);

        if ($statusFilter) {
            if ($statusFilter === 'recovered') {
                $collection->addFieldToFilter('recovered', 1);
            } else {
                $collection->addFieldToFilter('status', $statusFilter);
            }
        }

        $output->writeln('<comment>📋 Carrinhos Recentes (últimos 20):</comment>');

        $table = new Table($output);
        $table->setHeaders(['ID', 'Email', 'Valor', 'Itens', 'Status', 'E1', 'E2', 'E3', 'Data']);

        foreach ($collection as $item) {
            $status = $item->isRecovered() ? '✅ Recuperado' : $item->getStatus();
            $table->addRow([
                $item->getEntityId(),
                substr($item->getCustomerEmail(), 0, 25),
                'R$ ' . number_format($item->getCartValue(), 2, ',', '.'),
                $item->getItemsCount(),
                $status,
                $item->isEmail1Sent() ? '✓' : '-',
                $item->isEmail2Sent() ? '✓' : '-',
                $item->isEmail3Sent() ? '✓' : '-',
                date('d/m H:i', strtotime($item->getAbandonedAt())),
            ]);
        }

        $table->render();
    }
}
