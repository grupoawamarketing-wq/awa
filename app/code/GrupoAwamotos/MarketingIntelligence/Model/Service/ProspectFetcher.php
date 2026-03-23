<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model\Service;

use GrupoAwamotos\MarketingIntelligence\Api\Data\ProspectInterface;
use GrupoAwamotos\MarketingIntelligence\Api\ProspectRepositoryInterface;
use GrupoAwamotos\MarketingIntelligence\Model\ProspectFactory;
use GrupoAwamotos\B2B\Model\CnaeClassifier;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Fetches B2B prospects from CNPJ.ws API by CNAE code.
 */
class ProspectFetcher
{
    private const API_BASE_URL = 'https://comercial.cnpj.ws/cnpj';
    private const XML_PATH_ENABLED = 'marketing_intelligence/prospect_api/enabled';
    private const XML_PATH_API_TOKEN = 'marketing_intelligence/prospect_api/api_token';
    private const XML_PATH_CNAES = 'marketing_intelligence/prospect_api/cnaes';
    private const XML_PATH_UFS = 'marketing_intelligence/prospect_api/ufs';
    private const XML_PATH_MIN_CAPITAL = 'marketing_intelligence/prospect_api/min_capital';
    private const XML_PATH_BATCH_SIZE = 'marketing_intelligence/prospect_api/batch_size';
    private const DEFAULT_BATCH_SIZE = 50;
    private const REQUEST_TIMEOUT = 30;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ClientInterface $httpClient,
        private readonly Json $json,
        private readonly ProspectRepositoryInterface $prospectRepository,
        private readonly ProspectFactory $prospectFactory,
        private readonly CnaeClassifier $cnaeClassifier,
        private readonly ProspectScorer $prospectScorer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return int Number of prospects fetched/updated
     */
    public function execute(): int
    {
        if (!$this->isEnabled()) {
            $this->logger->info('ProspectFetcher: disabled via config.');
            return 0;
        }

        $token = $this->getApiToken();
        if (empty($token)) {
            $this->logger->error('ProspectFetcher: API token not configured.');
            return 0;
        }

        $cnaes = $this->getCnaeCodes();
        if (empty($cnaes)) {
            $this->logger->warning('ProspectFetcher: no CNAE codes configured, using CnaeClassifier defaults.');
            $cnaes = array_merge(
                $this->cnaeClassifier->getDirectCnaes(),
                $this->cnaeClassifier->getAdjacentCnaes()
            );
        }

        $ufs = $this->getUfs();
        $minCapital = $this->getMinCapital();
        $batchSize = $this->getBatchSize();
        $totalFetched = 0;

        foreach ($cnaes as $cnae) {
            try {
                $fetched = $this->fetchByCnae($token, $cnae, $ufs, $minCapital, $batchSize);
                $totalFetched += $fetched;
                $this->logger->info(sprintf(
                    'ProspectFetcher: CNAE %s — %d prospects fetched.',
                    $cnae,
                    $fetched
                ));
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'ProspectFetcher: error fetching CNAE %s — %s',
                    $cnae,
                    $e->getMessage()
                ));
            }
        }

        $this->logger->info(sprintf('ProspectFetcher: total %d prospects processed.', $totalFetched));
        return $totalFetched;
    }

    /**
     * @param string[] $ufs
     */
    private function fetchByCnae(
        string $token,
        string $cnae,
        array $ufs,
        float $minCapital,
        int $batchSize
    ): int {
        $page = 1;
        $fetched = 0;

        do {
            $params = [
                'cnaes' => $cnae,
                'situacao_cadastral' => 'ATIVA',
                'pagina' => $page,
                'quantidade' => $batchSize,
            ];

            if (!empty($ufs)) {
                $params['uf'] = implode(',', $ufs);
            }

            if ($minCapital > 0) {
                $params['capital_social_min'] = $minCapital;
            }

            $response = $this->callApi($token, $params);
            if ($response === null || empty($response['data'])) {
                break;
            }

            foreach ($response['data'] as $company) {
                $saved = $this->processCompany($company, $cnae);
                if ($saved) {
                    $fetched++;
                }
            }

            $hasMore = isset($response['pages']) && $page < (int)$response['pages'];
            $page++;
        } while ($hasMore);

        return $fetched;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function callApi(string $token, array $params): ?array
    {
        $url = self::API_BASE_URL . '?' . http_build_query($params);

        try {
            $this->httpClient->setTimeout(self::REQUEST_TIMEOUT);
            $this->httpClient->setHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ]);
            $this->httpClient->get($url);

            $status = $this->httpClient->getStatus();
            $body = $this->httpClient->getBody();

            if ($status === 429) {
                $this->logger->warning('ProspectFetcher: rate limited by CNPJ.ws, stopping.');
                return null;
            }

            if ($status !== 200) {
                $this->logger->error(sprintf(
                    'ProspectFetcher: API returned HTTP %d — %s',
                    $status,
                    mb_substr($body, 0, 500)
                ));
                return null;
            }

            return $this->json->unserialize($body);
        } catch (\Exception $e) {
            $this->logger->error('ProspectFetcher: HTTP request failed — ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @param array<string, mixed> $company
     */
    private function processCompany(array $company, string $searchCnae): bool
    {
        $cnpj = $this->sanitizeCnpj($company['cnpj'] ?? '');
        if (empty($cnpj)) {
            return false;
        }

        try {
            $prospect = $this->prospectRepository->getByCnpj($cnpj);
        } catch (NoSuchEntityException) {
            $prospect = $this->prospectFactory->create();
            $prospect->setCnpj($cnpj);
        }

        $prospect->setRazaoSocial((string)($company['razao_social'] ?? ''));
        $prospect->setNomeFantasia($company['nome_fantasia'] ?? null);

        $cnaePrincipal = $company['cnae_fiscal_principal']['codigo'] ?? $searchCnae;
        $cnaeDescricao = $company['cnae_fiscal_principal']['descricao'] ?? '';
        $prospect->setCnaePrincipal((string)$cnaePrincipal);
        $prospect->setCnaeDescricao((string)$cnaeDescricao);

        $profile = $this->cnaeClassifier->classify((string)$cnaePrincipal);
        $prospect->setCnaeProfile($profile);

        $endereco = $company['estabelecimento'] ?? $company;
        $prospect->setUf((string)($endereco['uf'] ?? ''));
        $prospect->setMunicipio((string)($endereco['cidade']['nome'] ?? $endereco['municipio'] ?? ''));
        $prospect->setCep((string)($endereco['cep'] ?? ''));

        $prospect->setEmail($endereco['email'] ?? null);
        $prospect->setTelefone($this->buildTelefone($endereco));

        $capitalSocial = (float)($company['capital_social'] ?? 0);
        $prospect->setCapitalSocial($capitalSocial);
        $prospect->setPorte($company['porte']['descricao'] ?? null);
        $prospect->setDataAbertura($company['data_inicio_atividade'] ?? null);
        $prospect->setSituacaoCadastral($company['situacao_cadastral'] ?? 'ATIVA');
        $prospect->setSource('cnpj_ws');
        $prospect->setFetchedAt(date('Y-m-d H:i:s'));

        $score = $this->prospectScorer->score($prospect);
        $prospect->setProspectScore($score);

        try {
            $this->prospectRepository->save($prospect);
            return true;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'ProspectFetcher: failed saving CNPJ %s — %s',
                $cnpj,
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * @param array<string, mixed> $endereco
     */
    private function buildTelefone(array $endereco): ?string
    {
        $ddd = $endereco['ddd1'] ?? '';
        $telefone = $endereco['telefone1'] ?? '';

        if (empty($ddd) && empty($telefone)) {
            return null;
        }

        return trim($ddd . $telefone);
    }

    private function sanitizeCnpj(string $cnpj): string
    {
        return preg_replace('/\D/', '', $cnpj) ?? '';
    }

    private function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED);
    }

    private function getApiToken(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_API_TOKEN);
    }

    /**
     * @return string[]
     */
    private function getCnaeCodes(): array
    {
        $value = (string)$this->scopeConfig->getValue(self::XML_PATH_CNAES);
        if (empty($value)) {
            return [];
        }

        return array_filter(
            array_map('trim', explode("\n", $value))
        );
    }

    /**
     * @return string[]
     */
    private function getUfs(): array
    {
        $value = (string)$this->scopeConfig->getValue(self::XML_PATH_UFS);
        if (empty($value)) {
            return [];
        }

        return array_filter(
            array_map('trim', explode(',', $value))
        );
    }

    private function getMinCapital(): float
    {
        return (float)$this->scopeConfig->getValue(self::XML_PATH_MIN_CAPITAL);
    }

    private function getBatchSize(): int
    {
        $size = (int)$this->scopeConfig->getValue(self::XML_PATH_BATCH_SIZE);
        return $size > 0 ? $size : self::DEFAULT_BATCH_SIZE;
    }
}
