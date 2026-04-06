<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;

/**
 * Converte nomes de produtos ALL CAPS (vindos do ERP) para Title Case inteligente.
 *
 * Somente atua em frontend. Preserva abreviações de motos e códigos curtos.
 *
 * Exemplos:
 *   "RET. BIZ 100 CR. REDONDO UNIVERSAL" → "Ret. Biz 100 CR. Redondo Universal"
 *   "RETROVISOR CB 300 MODELO 11 DIR/ESQ" → "Retrovisor CB 300 Modelo 11 DIR/ESQ"
 *   "BAULETO AWA MODELO PROOS 41 LITROS"  → "Bauleto AWA Modelo Proos 41 Litros"
 */
class ProductNameTitleCasePlugin
{
    /**
     * Palavras que devem permanecer em maiúsculas independente do tamanho.
     * Inclui marcas (AWA), séries de motos (CBX, BIZ, etc.) e abreviações técnicas.
     */
    private const FORCE_UPPERCASE = [
        'AWA',
        'CBX',
        'BIZ',
        'NXR',
        'XRE',
        'YBR',
        'GSX',
        'CRF',
        'XTZ',
        'PCX',
        'LED',
        'ABS',
        'EFI',
        'POP',
    ];

    /**
     * Preposições e artigos PT-BR que devem ficar em minúsculas (exceto início de frase).
     */
    private const LOWERCASE_WORDS = [
        'de', 'do', 'da', 'dos', 'das',
        'em', 'no', 'na', 'nos', 'nas',
        'ao', 'os', 'as', 'um', 'ou',
        'com', 'sem', 'por',
    ];

    public function __construct(
        private readonly State $appState,
    ) {
    }

    /**
     * Transforma o nome do produto para Title Case quando ALL CAPS.
     *
     * @param Product $subject
     * @param string|null $result
     * @return string|null
     */
    public function afterGetName(Product $subject, ?string $result): ?string
    {
        if ($result === null || $result === '') {
            return $result;
        }

        // Não transforma no admin ou API — apenas frontend
        try {
            $area = $this->appState->getAreaCode();
            if ($area !== Area::AREA_FRONTEND) {
                return $result;
            }
        } catch (\Exception) {
            // Area code não definido (CLI, cron) — não transforma
            return $result;
        }

        // Só transforma se o nome for ALL CAPS (vindo do ERP)
        // Se já tiver mixed case, respeita o original
        if ($result !== mb_strtoupper($result, 'UTF-8')) {
            return $result;
        }

        return $this->toTitleCase($result);
    }

    /**
     * Transforma meta_title para Title Case quando ALL CAPS (PDP-001).
     *
     * O Magento usa getMetaTitle() para o <title> HTML do produto, com fallback para
     * getName(). Se o ERP popula meta_title em ALL CAPS, o título da página fica em
     * maiúsculas mesmo com o afterGetName() ativo.
     *
     * @param Product $subject
     * @param string|null $result
     * @return string|null
     */
    public function afterGetMetaTitle(Product $subject, ?string $result): ?string
    {
        if ($result === null || $result === '') {
            return $result;
        }

        try {
            $area = $this->appState->getAreaCode();
            if ($area !== Area::AREA_FRONTEND) {
                return $result;
            }
        } catch (\Exception) {
            return $result;
        }

        if ($result !== mb_strtoupper($result, 'UTF-8')) {
            return $result;
        }

        return $this->toTitleCase($result);
    }

    /**
     * Converte ALL CAPS para Title Case preservando abreviações.
     *
     * Regras:
     * 1. Converte tudo para Title Case via mb_convert_case
     * 2. Re-uppercase palavras de 1-2 letras (CB, CG, XL, D, E, CR, etc.)
     * 3. Re-uppercase palavras da whitelist (AWA, CBX, BIZ, etc.)
     * 4. Re-uppercase abreviações com barra (D/E, DIR/ESQ)
     *
     * @param string $name
     * @return string
     */
    private function toTitleCase(string $name): string
    {
        // Step 1: Title Case base
        $result = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');

        // Step 2: Re-uppercase palavras de 1-2 caracteres alfabéticos (abreviações comuns)
        // Exclui preposições PT-BR (de, do, da, em, no, na, ao, etc.)
        $lowercaseMap = array_flip(self::LOWERCASE_WORDS);
        $result = (string) preg_replace_callback(
            '/\b([a-zA-ZÀ-ÿ]{1,2})\b/u',
            static function (array $m) use ($lowercaseMap): string {
                $lower = mb_strtolower($m[1], 'UTF-8');
                if (isset($lowercaseMap[$lower])) {
                    return $lower;
                }
                return mb_strtoupper($m[1], 'UTF-8');
            },
            $result
        );

        // Step 2b: Re-lowercase preposições de 3 letras (com, sem, por, dos, das, nos, nas)
        $result = (string) preg_replace_callback(
            '/\b(' . implode('|', array_filter(self::LOWERCASE_WORDS, static fn(string $w) => mb_strlen($w) === 3)) . ')\b/iu',
            static fn(array $m): string => mb_strtolower($m[1], 'UTF-8'),
            $result
        );

        // Garante que a primeira palavra do nome é capitalizada
        $result = mb_strtoupper(mb_substr($result, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($result, 1, null, 'UTF-8');

        // Step 3: Re-uppercase palavras da whitelist
        $pattern = '/\b(' . implode('|', array_map(
            static fn(string $w): string => preg_quote(mb_convert_case($w, MB_CASE_TITLE, 'UTF-8'), '/'),
            self::FORCE_UPPERCASE
        )) . ')\b/u';

        $result = (string) preg_replace_callback(
            $pattern,
            static fn(array $m): string => mb_strtoupper($m[1], 'UTF-8'),
            $result
        );

        // Step 4: Re-uppercase abreviações com barra (D/E, DIR/ESQ, CG/ML)
        $result = (string) preg_replace_callback(
            '/\b([a-zA-Z]{1,3})\/([a-zA-Z]{1,3})\b/',
            static fn(array $m): string => mb_strtoupper($m[1] . '/' . $m[2], 'UTF-8'),
            $result
        );

        return $result;
    }
}
