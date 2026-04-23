<?php
declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin;

use Rokanthemes\SlideBanner\Block\Slider;

/**
 * LCP-001: Remove decoding="async" da primeira imagem do hero slider.
 *
 * Problema raiz: HeroPreloadPlugin (Theme) converte decoding="async" → "sync"
 * em imagens com loading="eager". Com decoding="sync" e throttling 6× de CPU
 * (Lighthouse mobile), o decode bloqueia o main thread por ~20s, causando:
 *   - TBT: 2,300ms → 7,110ms
 *   - CLS: 0.004 → 0.131 (CSS async aplica após o unblock, causando shift)
 *
 * Este plugin remove decoding="async" da primeira imagem (isFirst=true) ANTES
 * do HeroPreloadPlugin executar. Sem o atributo, o regex do HeroPreloadPlugin
 * não faz match e a imagem fica com decoding implícito "auto" — comportamento
 * ótimo para imagem LCP com fetchpriority="high".
 *
 * Ordem de execução: sortOrder=10 (antes) → HeroPreloadPlugin não encontra "async".
 *
 * @see GrupoAwamotos\Theme\Plugin\SlideBanner\HeroPreloadPlugin::afterToHtml()
 */
final class SliderLcpDecodingPlugin
{
    public function afterGetImageElement(
        Slider $subject,
        string $result,
        string $src,
        string $altText = '',
        bool $isFirst = false
    ): string {
        if ($isFirst) {
            return str_replace(' decoding="async"', '', $result);
        }
        return $result;
    }

    public function afterGetImageElementMobile(
        Slider $subject,
        string $result,
        string $src,
        string $altText = '',
        bool $isFirst = false
    ): string {
        if ($isFirst) {
            return str_replace(' decoding="async"', '', $result);
        }
        return $result;
    }
}
