<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model\Service;

use Meta\BusinessExtension\Helper\FBEHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Validates Meta API access: tests token, ad account, and detects available permissions.
 */
class MetaConfigValidator
{
    private const XML_PATH_AD_ACCOUNT_ID = 'marketing_intelligence/meta_audiences/ad_account_id';
    private const XML_PATH_SYSTEM_USER_TOKEN = 'marketing_intelligence/meta_audiences/system_user_token';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly FBEHelper $fbeHelper,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Validate Meta API access and return capabilities.
     *
     * @return array{success: bool, user: array, account: array, permissions: array<string>, errors: array<string>}
     */
    public function validate(): array
    {
        $result = [
            'success' => false,
            'user' => [],
            'account' => [],
            'permissions' => [],
            'errors' => [],
        ];

        $token = (string) $this->scopeConfig->getValue(self::XML_PATH_SYSTEM_USER_TOKEN);
        if (empty($token)) {
            $result['errors'][] = 'System User Token não configurado.';
            return $result;
        }

        $adAccountId = $this->getAdAccountId();
        if (empty($adAccountId)) {
            $result['errors'][] = 'Ad Account ID não configurado.';
            return $result;
        }

        // Step 1: Validate token with /me
        try {
            $meResponse = $this->fbeHelper->apiGet('/me', ['fields' => 'id,name']);
            if (empty($meResponse['id'])) {
                $result['errors'][] = 'Token inválido ou expirado — /me não retornou ID.';
                return $result;
            }
            $result['user'] = [
                'id' => $meResponse['id'],
                'name' => $meResponse['name'] ?? 'N/A',
            ];
        } catch (\Exception $e) {
            $result['errors'][] = 'Erro ao validar token: ' . $e->getMessage();
            $this->logger->error('MetaConfigValidator: /me failed — ' . $e->getMessage());
            return $result;
        }

        // Step 2: Validate Ad Account
        try {
            $accountResponse = $this->fbeHelper->apiGet(
                sprintf('/%s', $adAccountId),
                ['fields' => 'name,account_status,currency,business_name']
            );
            if (empty($accountResponse['name'])) {
                $result['errors'][] = 'Ad Account não encontrado ou sem permissão.';
                return $result;
            }
            $result['account'] = [
                'id' => $adAccountId,
                'name' => $accountResponse['name'],
                'status' => $this->getAccountStatusLabel((int) ($accountResponse['account_status'] ?? 0)),
                'currency' => $accountResponse['currency'] ?? 'N/A',
                'business_name' => $accountResponse['business_name'] ?? 'N/A',
            ];
        } catch (\Exception $e) {
            $result['errors'][] = 'Erro ao validar Ad Account: ' . $e->getMessage();
            $this->logger->error('MetaConfigValidator: ad account check failed — ' . $e->getMessage());
            return $result;
        }

        // Step 3: Detect permissions by probing endpoints
        $result['permissions'] = $this->detectPermissions($adAccountId);
        $result['success'] = true;

        $this->logger->info(sprintf(
            'MetaConfigValidator: success. User=%s, Account=%s, Permissions=[%s]',
            $result['user']['name'],
            $result['account']['name'],
            implode(', ', $result['permissions'])
        ));

        return $result;
    }

    /**
     * Probe endpoints to detect available permissions.
     *
     * @return array<string>
     */
    private function detectPermissions(string $adAccountId): array
    {
        $permissions = [];

        // Test ads_read — try fetching insights
        try {
            $this->fbeHelper->apiGet(
                sprintf('/%s/insights', $adAccountId),
                ['fields' => 'spend', 'date_preset' => 'today', 'limit' => '1']
            );
            $permissions[] = 'ads_read';
        } catch (\Exception $e) {
            $this->logger->info('MetaConfigValidator: ads_read not available — ' . $e->getMessage());
        }

        // Test ads_management — try fetching custom audiences
        try {
            $this->fbeHelper->apiGet(
                sprintf('/%s/customaudiences', $adAccountId),
                ['fields' => 'id', 'limit' => '1']
            );
            $permissions[] = 'ads_management';
        } catch (\Exception $e) {
            $this->logger->info('MetaConfigValidator: ads_management not available — ' . $e->getMessage());
        }

        // Test business_management
        try {
            $this->fbeHelper->apiGet(
                sprintf('/%s/adcreatives', $adAccountId),
                ['fields' => 'id', 'limit' => '1']
            );
            $permissions[] = 'business_management';
        } catch (\Exception $e) {
            $this->logger->info('MetaConfigValidator: business_management not available — ' . $e->getMessage());
        }

        return $permissions;
    }

    private function getAdAccountId(): string
    {
        $id = (string) $this->scopeConfig->getValue(self::XML_PATH_AD_ACCOUNT_ID);
        if (empty($id)) {
            return '';
        }
        if (!str_starts_with($id, 'act_')) {
            $id = 'act_' . $id;
        }
        return $id;
    }

    private function getAccountStatusLabel(int $status): string
    {
        return match ($status) {
            1 => 'Ativo',
            2 => 'Desabilitado',
            3 => 'Não aprovado',
            7 => 'Aprovação pendente',
            9 => 'Em período de carência',
            100 => 'Restrito temporariamente',
            101 => 'Suspenso',
            default => 'Desconhecido (' . $status . ')',
        };
    }
}
