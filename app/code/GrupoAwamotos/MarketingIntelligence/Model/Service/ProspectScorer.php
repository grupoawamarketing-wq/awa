<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model\Service;

use GrupoAwamotos\MarketingIntelligence\Api\Data\ProspectInterface;
use GrupoAwamotos\B2B\Model\CnaeClassifier;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Scores B2B prospects 0-100 based on CNAE profile, capital, UF proximity, etc.
 */
class ProspectScorer
{
    private const XML_PATH_UFS = 'marketing_intelligence/prospect_api/ufs';

    /** Weight distribution (total = 100) */
    private const WEIGHT_CNAE = 40;
    private const WEIGHT_CAPITAL = 20;
    private const WEIGHT_PORTE = 15;
    private const WEIGHT_UF = 15;
    private const WEIGHT_SITUACAO = 10;

    /** Capital thresholds for scoring */
    private const CAPITAL_HIGH = 500000.0;
    private const CAPITAL_MEDIUM = 100000.0;
    private const CAPITAL_LOW = 10000.0;

    /** Porte scoring map */
    private const PORTE_SCORES = [
        'GRANDE' => 1.0,
        'MEDIO' => 0.8,
        'PEQUENO' => 0.6,
        'MICRO' => 0.4,
    ];

    /** Priority UFs (SP nearness) */
    private const UF_HOME = 'SP';
    private const UF_NEIGHBORS = ['MG', 'RJ', 'PR', 'MS', 'GO'];

    public function __construct(
        private readonly CnaeClassifier $cnaeClassifier,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger
    ) {
    }

    public function score(ProspectInterface $prospect): int
    {
        $cnaeScore = $this->scoreCnae($prospect->getCnaeProfile());
        $capitalScore = $this->scoreCapital($prospect->getCapitalSocial());
        $porteScore = $this->scorePorte($prospect->getPorte());
        $ufScore = $this->scoreUf($prospect->getUf());
        $situacaoScore = $this->scoreSituacao($prospect->getSituacaoCadastral());

        $total = (int)round(
            ($cnaeScore * self::WEIGHT_CNAE)
            + ($capitalScore * self::WEIGHT_CAPITAL)
            + ($porteScore * self::WEIGHT_PORTE)
            + ($ufScore * self::WEIGHT_UF)
            + ($situacaoScore * self::WEIGHT_SITUACAO)
        );

        return max(0, min(100, $total));
    }

    /**
     * CNAE profile: direct=1.0, adjacent=0.5, off=0.1
     */
    private function scoreCnae(?string $profile): float
    {
        return match ($profile) {
            CnaeClassifier::PROFILE_DIRECT => 1.0,
            CnaeClassifier::PROFILE_ADJACENT => 0.5,
            default => 0.1,
        };
    }

    /**
     * Capital social: scaled 0-1 with diminishing returns above HIGH threshold.
     */
    private function scoreCapital(?float $capital): float
    {
        if ($capital === null || $capital <= 0) {
            return 0.0;
        }

        if ($capital >= self::CAPITAL_HIGH) {
            return 1.0;
        }

        if ($capital >= self::CAPITAL_MEDIUM) {
            return 0.6 + 0.4 * (($capital - self::CAPITAL_MEDIUM) / (self::CAPITAL_HIGH - self::CAPITAL_MEDIUM));
        }

        if ($capital >= self::CAPITAL_LOW) {
            return 0.2 + 0.4 * (($capital - self::CAPITAL_LOW) / (self::CAPITAL_MEDIUM - self::CAPITAL_LOW));
        }

        return 0.1;
    }

    /**
     * Porte (size): mapped from GRANDE(1.0) down to MICRO(0.4).
     */
    private function scorePorte(?string $porte): float
    {
        if ($porte === null) {
            return 0.3;
        }

        $normalized = mb_strtoupper(trim($porte));
        foreach (self::PORTE_SCORES as $key => $score) {
            if (str_contains($normalized, $key)) {
                return $score;
            }
        }

        return 0.3;
    }

    /**
     * UF proximity: home state=1.0, configured UFs=0.8, neighbors=0.5, others=0.2.
     */
    private function scoreUf(?string $uf): float
    {
        if ($uf === null || $uf === '') {
            return 0.1;
        }

        $normalized = mb_strtoupper(trim($uf));

        if ($normalized === self::UF_HOME) {
            return 1.0;
        }

        $configuredUfs = $this->getConfiguredUfs();
        if (!empty($configuredUfs) && in_array($normalized, $configuredUfs, true)) {
            return 0.8;
        }

        if (in_array($normalized, self::UF_NEIGHBORS, true)) {
            return 0.5;
        }

        return 0.2;
    }

    /**
     * Situação cadastral: ATIVA=1.0, everything else=0.0.
     */
    private function scoreSituacao(?string $situacao): float
    {
        if ($situacao === null) {
            return 0.0;
        }

        return mb_strtoupper(trim($situacao)) === 'ATIVA' ? 1.0 : 0.0;
    }

    /**
     * @return string[]
     */
    private function getConfiguredUfs(): array
    {
        $value = (string)$this->scopeConfig->getValue(self::XML_PATH_UFS);
        if (empty($value)) {
            return [];
        }

        return array_map(
            fn(string $uf) => mb_strtoupper(trim($uf)),
            explode(',', $value)
        );
    }
}
