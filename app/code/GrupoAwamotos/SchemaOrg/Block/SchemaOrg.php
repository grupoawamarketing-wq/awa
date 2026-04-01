<?php

/**
 * SchemaOrg Block — Fallback/Compatibility
 *
 * This class exists because a CMS page directive references
 * {{block class="GrupoAwamotos\SchemaOrg\Block\SchemaOrg"...}}.
 * Rather than editing the CMS content in the database, we provide
 * this thin wrapper that simply extends Template.
 *
 * @see \GrupoAwamotos\SchemaOrg\Block\ProductSchema
 */

declare(strict_types=1);

namespace GrupoAwamotos\SchemaOrg\Block;

use Magento\Framework\View\Element\Template;

class SchemaOrg extends Template
{
    // Intentionally empty — serves as a CMS-directive fallback.
    // The actual rendering is done by the template assigned in
    // the {{block}} directive within the CMS page content.
}
