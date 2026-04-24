<?php
declare(strict_types=1);

namespace GrupoAwamotos\Theme\Plugin\SlideBanner;

use Rokanthemes\SlideBanner\Block\Slider;

/**
 * Plugin no bloco Slider do Rokanthemes SlideBanner.
 *
 * Responsabilidades:
 * - afterToHtml: corrige decoding="async" → "sync" nas imagens LCP (loading="eager").
 *
 * Preloads do hero são gerenciados por awa-head-preload.phtml via
 * GrupoAwamotos\Theme\Block\Html\HeroPreload (usa imagesrcset para compatibilidade
 * com <source srcset> em elementos <picture>).
 */
class HeroPreloadPlugin
{
    /**
     * Antes de renderizar o HTML do slider, retorna null sem side-effects.
     * Preloads são gerenciados por awa-head-preload.phtml.
     *
     * @param Slider $subject
     * @return null
     */
    public function beforeToHtml(Slider $subject): ?array
    {
        return null;
    }

    /**
     * Corrige decoding="async" → "sync" nas imagens LCP do slider.
     *
     * decoding="async" adia o decode para um task off-thread, mas sob throttling
     * de CPU 4x (Lighthouse) a tarefa pode ser postergada, atrasando o paint e
     * causando NO_LCP. Somente imagens com loading="eager" são afetadas (hero).
     *
     * @param Slider $subject
     * @param string $result HTML gerado pelo bloco
     * @return string
     */
    public function afterToHtml(Slider $subject, string $result): string
    {
        if (!str_contains($result, 'decoding="async"')) {
            return $result;
        }
        // Substitui decoding=async somente em <img> que tenham loading="eager"
        return (string) preg_replace(
            '/(<img\b[^>]*loading="eager"[^>]*)decoding="async"/',
            '$1decoding="sync"',
            $result
        );
    }
}
