<?php

/**
 * GrupoAwamotos_CspFix
 *
 * Magento's File::load() reads deployed_version.txt without trim().
 * If the file has a trailing newline (e.g., written via shell `echo`),
 * the newline gets embedded in the RequireJS baseUrl, breaking all
 * admin JS (menus, save buttons stop working).
 *
 * This plugin trims the loaded value before it reaches Version::readValue().
 */

declare(strict_types=1);

namespace GrupoAwamotos\CspFix\Plugin\Deployment\Version\Storage;

use Magento\Framework\App\View\Deployment\Version\Storage\File;

class FileTrimPlugin
{
    /**
     * Trim whitespace (including \n) from the loaded static version string.
     */
    public function afterLoad(File $subject, $result): string|false
    {
        if ($result === false || $result === null) {
            return false;
        }
        return trim((string) $result);
    }
}
