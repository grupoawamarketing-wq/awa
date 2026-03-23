<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Controller\Webhook;

use GrupoAwamotos\MarketingIntelligence\Api\Data\ProspectInterface;
use GrupoAwamotos\MarketingIntelligence\Api\Data\ProspectInterfaceFactory;
use GrupoAwamotos\MarketingIntelligence\Api\ProspectRepositoryInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;
use Psr\Log\LoggerInterface;

/**
 * Receives Meta Lead Ads webhook POST data and saves as Prospect.
 *
 * URL: POST /marketingintelligence/webhook/leadads
 *
 * Meta sends JSON with leadgen_id, form_id, and field_data containing
 * form field values (name, email, phone, cnpj, etc.)
 *
 * Token verification: X-Webhook-Token header OR ?token= query param
 * must match marketing_intelligence/lead_ads/webhook_token config.
 */
class LeadAds implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const XML_PATH_ENABLED = 'marketing_intelligence/lead_ads/enabled';
    private const XML_PATH_TOKEN = 'marketing_intelligence/lead_ads/webhook_token';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ProspectInterfaceFactory $prospectFactory,
        private readonly ProspectRepositoryInterface $prospectRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        // Webhooks are external — verify by token instead of CSRF form key
        return true;
    }

    /**
     * Process Lead Ads webhook payload.
     */
    public function execute(): Json
    {
        $result = $this->jsonFactory->create();

        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED)) {
            return $result->setData(['status' => 'error', 'message' => 'Lead Ads webhook disabled']);
        }

        if (!$this->verifyToken()) {
            $this->logger->warning('LeadAds webhook: invalid token');
            return $result->setHttpResponseCode(403)
                ->setData(['status' => 'error', 'message' => 'Invalid token']);
        }

        try {
            $body = $this->request->getContent();
            $payload = json_decode((string) $body, true);

            if (!is_array($payload) || empty($payload)) {
                return $result->setHttpResponseCode(400)
                    ->setData(['status' => 'error', 'message' => 'Invalid payload']);
            }

            $saved = $this->processPayload($payload);

            $this->logger->info('LeadAds webhook: processed lead data', [
                'leads_saved' => $saved,
            ]);

            return $result->setData(['status' => 'ok', 'leads_saved' => $saved]);
        } catch (\Exception $e) {
            $this->logger->error('LeadAds webhook: processing failed', [
                'error' => $e->getMessage(),
            ]);
            return $result->setHttpResponseCode(500)
                ->setData(['status' => 'error', 'message' => 'Internal error']);
        }
    }

    /**
     * Verify webhook token from header or query param.
     */
    private function verifyToken(): bool
    {
        $configToken = (string) $this->scopeConfig->getValue(self::XML_PATH_TOKEN);
        if (empty($configToken)) {
            return false;
        }

        // Check X-Webhook-Token header first, then query param
        $token = $this->request->getHeader('X-Webhook-Token');
        if (empty($token)) {
            $token = $this->request->getParam('token', '');
        }

        return hash_equals($configToken, (string) $token);
    }

    /**
     * Process the Meta Lead Ads payload and save as Prospect(s).
     *
     * Meta sends: { "entry": [{ "changes": [{ "value": { "leadgen_id": "...", "form_id": "...", "field_data": [...] } }] }] }
     * Or simplified: { "leadgen_id": "...", "form_id": "...", "field_data": [...] }
     *
     * @return int Number of leads saved
     */
    private function processPayload(array $payload): int
    {
        $leads = $this->extractLeads($payload);
        $saved = 0;

        foreach ($leads as $lead) {
            try {
                $fields = $this->mapFieldData($lead['field_data'] ?? []);
                if (empty($fields['cnpj']) && empty($fields['email'])) {
                    $this->logger->debug('LeadAds: skipping lead without CNPJ or email', [
                        'leadgen_id' => $lead['leadgen_id'] ?? 'unknown',
                    ]);
                    continue;
                }

                $prospect = $this->createProspect($fields, $lead);
                $this->prospectRepository->save($prospect);
                $saved++;
            } catch (\Exception $e) {
                $this->logger->error('LeadAds: failed to save lead', [
                    'leadgen_id' => $lead['leadgen_id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $saved;
    }

    /**
     * Extract individual leads from the Meta webhook payload structure.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractLeads(array $payload): array
    {
        // Standard Meta webhook format: { "entry": [{ "changes": [{ "value": {...} }] }] }
        if (isset($payload['entry']) && is_array($payload['entry'])) {
            $leads = [];
            foreach ($payload['entry'] as $entry) {
                foreach ($entry['changes'] ?? [] as $change) {
                    if (isset($change['value']) && is_array($change['value'])) {
                        $leads[] = $change['value'];
                    }
                }
            }
            return $leads;
        }

        // Simplified format: direct lead object
        if (isset($payload['field_data']) || isset($payload['leadgen_id'])) {
            return [$payload];
        }

        return [];
    }

    /**
     * Map Meta field_data array to key-value pairs.
     *
     * Meta sends: [{ "name": "email", "values": ["test@example.com"] }, ...]
     *
     * @return array<string, string>
     */
    private function mapFieldData(array $fieldData): array
    {
        $mapped = [];
        foreach ($fieldData as $field) {
            $name = strtolower(trim($field['name'] ?? ''));
            $value = $field['values'][0] ?? '';

            // Map common Meta field names to Prospect fields
            $mapping = [
                'email' => 'email',
                'phone_number' => 'telefone',
                'phone' => 'telefone',
                'full_name' => 'razao_social',
                'company_name' => 'razao_social',
                'nome_fantasia' => 'nome_fantasia',
                'razao_social' => 'razao_social',
                'cnpj' => 'cnpj',
                'city' => 'municipio',
                'state' => 'uf',
                'zip_code' => 'cep',
                'cep' => 'cep',
            ];

            $key = $mapping[$name] ?? $name;
            if (!empty($value)) {
                $mapped[$key] = (string) $value;
            }
        }

        return $mapped;
    }

    /**
     * Create a Prospect entity from mapped field data.
     */
    private function createProspect(array $fields, array $lead): ProspectInterface
    {
        /** @var ProspectInterface $prospect */
        $prospect = $this->prospectFactory->create();

        if (!empty($fields['cnpj'])) {
            // Sanitize CNPJ to digits only
            $cnpj = preg_replace('/\D/', '', $fields['cnpj']);
            $prospect->setCnpj((string) $cnpj);
        }

        if (!empty($fields['razao_social'])) {
            $prospect->setRazaoSocial($fields['razao_social']);
        }

        if (!empty($fields['nome_fantasia'])) {
            $prospect->setNomeFantasia($fields['nome_fantasia']);
        }

        if (!empty($fields['email'])) {
            $prospect->setEmail($fields['email']);
        }

        if (!empty($fields['telefone'])) {
            $prospect->setTelefone($fields['telefone']);
        }

        if (!empty($fields['municipio'])) {
            $prospect->setMunicipio($fields['municipio']);
        }

        if (!empty($fields['uf'])) {
            $prospect->setUf($fields['uf']);
        }

        if (!empty($fields['cep'])) {
            $prospect->setCep($fields['cep']);
        }

        $prospect->setSource('lead_ads');
        $prospect->setProspectStatus('new');
        $prospect->setFetchedAt(date('Y-m-d H:i:s'));

        // Store leadgen metadata in notes
        $notes = [];
        if (!empty($lead['leadgen_id'])) {
            $notes[] = 'leadgen_id: ' . $lead['leadgen_id'];
        }
        if (!empty($lead['form_id'])) {
            $notes[] = 'form_id: ' . $lead['form_id'];
        }
        if (!empty($notes)) {
            $prospect->setNotes(implode(' | ', $notes));
        }

        return $prospect;
    }
}
