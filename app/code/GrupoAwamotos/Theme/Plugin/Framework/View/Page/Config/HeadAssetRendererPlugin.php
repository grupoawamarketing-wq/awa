<?php
declare(strict_types=1);

namespace GrupoAwamotos\Theme\Plugin\Framework\View\Page\Config;

use Magento\Framework\View\Page\Config\Renderer;

/**
 * Converte links bloqueantes de Google Fonts herdados do tema pai (ayo_default)
 * em carregamento assíncrono via media="print" onload="this.media='all'".
 *
 * Problema: ayo/ayo_default/Magento_Theme/layout/default_head_blocks.xml adiciona:
 *   <link src="https://fonts.googleapis.com/css2?family=Rubik..." src_type="url"/>
 * Esse link é renderizado com media="all" (bloqueante), atrasando o FCP em ~779ms.
 */
class HeadAssetRendererPlugin
{
    private const RUBIK_URL_PATTERN = 'fonts.googleapis.com/css2?family=Rubik';

    /**
     * Converte o link do Google Fonts Rubik de bloqueante para assíncrono.
     *
     * @param Renderer $subject
     * @param string   $result
     * @return string
     */
    public function afterRenderHeadAssets(Renderer $subject, string $result): string
    {
        if (strpos($result, self::RUBIK_URL_PATTERN) === false) {
            return $result;
        }

        $result = preg_replace_callback(
            '/<link\s[^>]*' . preg_quote(self::RUBIK_URL_PATTERN, '/') . '[^>]*>/i',
            static function (array $matches): string {
                $original = $matches[0];

                if (strpos($original, 'media="print"') !== false
                    || strpos($original, "media='print'") !== false
                ) {
                    return $original;
                }

                if (!preg_match('/href=["\']([^"\']+)["\']/', $original, $hrefMatch)) {
                    return $original;
                }

                $href = htmlspecialchars($hrefMatch[1], ENT_QUOTES, 'UTF-8');

                return '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin/>'
                    . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>'
                    . '<link rel="stylesheet" type="text/css"'
                    . ' media="print" onload="this.media=\'all\'"'
                    . ' href="' . $href . '"/>'
                    . '<noscript><link rel="stylesheet" type="text/css"'
                    . ' href="' . $href . '"/></noscript>';
            },
            $result
        );

        return (string) $result;
    }
}
