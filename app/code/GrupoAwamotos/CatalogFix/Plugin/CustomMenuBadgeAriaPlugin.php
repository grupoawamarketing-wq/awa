<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin;

use Rokanthemes\CustomMenu\Block\Topmenu;

/**
 * NAV-001 — Injeta aria-hidden="true" nos badges do CustomMenu (Quente, Novo, etc.)
 *
 * Os badges são renderizados dentro do anchor do link de navegação:
 *   <a href="...">Categoria <span class="cat-label cat-label-hot">Quente</span></a>
 *
 * Sem aria-hidden, leitores de tela lêem o badge como parte do nome do link
 * ("Categoria Quente"), dificultando a navegação por voz. Com aria-hidden="true"
 * o badge permanece visível mas é ignorado pela árvore de acessibilidade.
 */
class CustomMenuBadgeAriaPlugin
{
    /**
     * Adiciona aria-hidden="true" a todas as spans .cat-label no HTML do menu.
     *
     * @param Topmenu $subject
     * @param string $result
     * @return string
     */
    public function afterGetCustomMenuHtml(Topmenu $subject, string $result): string
    {
        if ($result === '' || !str_contains($result, 'cat-label')) {
            return $result;
        }

        return (string) preg_replace(
            '/<span\s+class="(cat-label[^"]*)"/',
            '<span aria-hidden="true" class="$1"',
            $result
        );
    }
}
