<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Observer;

use GrupoAwamotos\CatalogFix\Model\InstantJsonFixer;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * After admin saves search/searchautocomplete config sections, Mirasvit's
 * OnConfigChanged::execute() regenerates instant.json via ConfigMaker::ensure().
 *
 * This observer fires on the same events (admin_system_config_changed_section_*)
 * with sortOrder=999 to run AFTER Mirasvit's observer, then fixes the
 * store_id=0 elasticsearch7 index names in instant.json.
 */
class FixInstantJsonAfterConfigSave implements ObserverInterface
{
    private InstantJsonFixer $fixer;

    public function __construct(InstantJsonFixer $fixer)
    {
        $this->fixer = $fixer;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $this->fixer->fix();
    }
}
