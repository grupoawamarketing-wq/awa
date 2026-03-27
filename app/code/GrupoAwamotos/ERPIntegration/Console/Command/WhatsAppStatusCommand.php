<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use GrupoAwamotos\ERPIntegration\Model\WhatsApp\ZApiClient;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;

/**
 * CLI command to check and test WhatsApp Z-API connection
 */
class WhatsAppStatusCommand extends Command
{
    private const OPTION_SEND_TEST = 'send-test';
    private const OPTION_PHONE = 'phone';
    private const OPTION_MESSAGE = 'message';
    private const OPTION_QR_CODE = 'qr-code';

    private ZApiClient $zapiClient;
    private Helper $helper;

    public function __construct(
        ZApiClient $zapiClient,
        Helper $helper,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->zapiClient = $zapiClient;
        $this->helper = $helper;
    }

    protected function configure(): void
    {
        $this->setName('erp:whatsapp:status')
            ->setDescription('Check WhatsApp Z-API connection status and send test messages')
            ->addOption(
                self::OPTION_SEND_TEST,
                't',
                InputOption::VALUE_NONE,
                'Send a test message'
            )
            ->addOption(
                self::OPTION_PHONE,
                'p',
                InputOption::VALUE_REQUIRED,
                'Phone number for test message (with DDD, e.g., 11999999999)'
            )
            ->addOption(
                self::OPTION_MESSAGE,
                'm',
                InputOption::VALUE_REQUIRED,
                'Custom message to send (optional)'
            )
            ->addOption(
                self::OPTION_QR_CODE,
                'r',
                InputOption::VALUE_NONE,
                'Display QR code URL for connecting WhatsApp'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<info>===== WhatsApp Z-API Status =====</info>');
        $output->writeln('');

        // Show configuration
        $this->showConfiguration($output);

        // Check if configured
        if (!$this->zapiClient->isConfigured()) {
            $output->writeln('<error>Z-API nao configurada. Configure Instance ID e Token no admin.</error>');
            return Command::FAILURE;
        }

        // Get status
        $status = $this->zapiClient->getStatus();
        $this->showStatus($output, $status);

        // QR Code option
        if ($input->getOption(self::OPTION_QR_CODE)) {
            $this->showQrCode($output);
        }

        // Send test message
        if ($input->getOption(self::OPTION_SEND_TEST)) {
            $phone = $input->getOption(self::OPTION_PHONE);

            if (!$phone) {
                // Try to get from admin config
                $phone = $this->helper->getWhatsAppAdminPhone();
            }

            if (!$phone) {
                $output->writeln('<error>Telefone nao informado. Use --phone=11999999999</error>');
                return Command::FAILURE;
            }

            $message = $input->getOption(self::OPTION_MESSAGE);
            $this->sendTestMessage($output, $phone, $message);
        }

        return Command::SUCCESS;
    }

    private function showConfiguration(OutputInterface $output): void
    {
        $output->writeln('<comment>Configuracao:</comment>');

        $instanceId = $this->helper->getZApiInstanceId();
        $token = $this->helper->getZApiToken();
        $clientToken = $this->helper->getZApiClientToken();

        $output->writeln(sprintf(
            '  Instance ID: %s',
            $instanceId ? $this->maskString($instanceId) : '<error>Nao configurado</error>'
        ));

        $output->writeln(sprintf(
            '  Token:       %s',
            $token ? $this->maskString($token) : '<error>Nao configurado</error>'
        ));

        $output->writeln(sprintf(
            '  Client Token: %s',
            $clientToken ? $this->maskString($clientToken) : '<comment>Nao configurado (algumas instancias exigem)</comment>'
        ));

        $output->writeln(sprintf(
            '  Habilitado: %s',
            $this->helper->isWhatsAppEnabled() ? '<info>Sim</info>' : '<comment>Nao</comment>'
        ));

        $output->writeln('');
    }

    private function showStatus(OutputInterface $output, array $status): void
    {
        $output->writeln('<comment>Status da Conexao:</comment>');

        if ($status['success'] && $status['connected']) {
            $output->writeln('  Status:   <info>Conectado</info>');
            if (isset($status['phone'])) {
                $output->writeln(sprintf('  Telefone: %s', $status['phone']));
            }
        } elseif ($status['success'] && !$status['connected']) {
            $output->writeln('  Status: <comment>Desconectado</comment>');
            $output->writeln('  <comment>Acesse o painel Z-API e escaneie o QR Code</comment>');
        } else {
            $output->writeln(sprintf('  Status: <error>Erro - %s</error>', $status['message']));
        }

        $output->writeln('');
    }

    private function showQrCode(OutputInterface $output): void
    {
        $output->writeln('<comment>QR Code:</comment>');

        $qrData = $this->zapiClient->getQrCode();

        if ($qrData && isset($qrData['value'])) {
            $output->writeln('  URL do QR Code:');
            $output->writeln(sprintf('  <info>%s</info>', $qrData['value']));
        } else {
            $output->writeln('  <comment>Nao foi possivel obter QR Code (ja conectado ou erro)</comment>');
        }

        $output->writeln('');
    }

    private function sendTestMessage(OutputInterface $output, string $phone, ?string $message): void
    {
        $output->writeln('<comment>Enviando mensagem de teste...</comment>');
        $output->writeln(sprintf('  Telefone: %s', $phone));

        if ($message) {
            $result = $this->zapiClient->sendTextMessage($phone, $message);
        } else {
            $result = $this->zapiClient->testConnection($phone);
            $output->writeln(sprintf('  Resultado: %s', $result['message']));
            return;
        }

        if ($result) {
            $output->writeln('  <info>Mensagem enviada com sucesso!</info>');
            if (isset($result['zapiMessageId'])) {
                $output->writeln(sprintf('  Message ID: %s', $result['zapiMessageId']));
            }
        } else {
            $output->writeln('  <error>Falha ao enviar mensagem</error>');
        }

        $output->writeln('');
    }

    private function maskString(string $value): string
    {
        $length = strlen($value);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 4) . str_repeat('*', $length - 8) . substr($value, -4);
    }
}
