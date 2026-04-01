<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Test\Unit\Helper;

use GrupoAwamotos\Fitment\Helper\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\Fitment\Helper\Config
 */
class ConfigTest extends TestCase
{
    private Config $config;
    private ScopeConfigInterface&MockObject $scopeConfig;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);

        $context = $this->createMock(Context::class);
        $context->method('getScopeConfig')->willReturn($this->scopeConfig);

        $this->config = new Config($context);
    }

    // ====================================================================
    // getSkuWeight
    // ====================================================================

    public function testGetSkuWeightReturnsConfiguredValue(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_SKU_WEIGHT, ScopeInterface::SCOPE_STORE, null)
            ->willReturn('5');

        $this->assertSame(5, $this->config->getSkuWeight());
    }

    public function testGetSkuWeightReturnsDefaultWhenZero(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_SKU_WEIGHT, ScopeInterface::SCOPE_STORE, null)
            ->willReturn('0');

        $this->assertSame(3, $this->config->getSkuWeight());
    }

    public function testGetSkuWeightReturnsDefaultWhenNull(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_SKU_WEIGHT, ScopeInterface::SCOPE_STORE, 1)
            ->willReturn(null);

        $this->assertSame(3, $this->config->getSkuWeight(1));
    }

    public function testGetSkuWeightReturnsDefaultWhenNegative(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_SKU_WEIGHT, ScopeInterface::SCOPE_STORE, null)
            ->willReturn('-1');

        $this->assertSame(3, $this->config->getSkuWeight());
    }

    // ====================================================================
    // getMetaKeywordWeight
    // ====================================================================

    public function testGetMetaKeywordWeightReturnsConfiguredValue(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_META_KEYWORD_WEIGHT, ScopeInterface::SCOPE_STORE, null)
            ->willReturn('4');

        $this->assertSame(4, $this->config->getMetaKeywordWeight());
    }

    public function testGetMetaKeywordWeightReturnsDefaultWhenZero(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_META_KEYWORD_WEIGHT, ScopeInterface::SCOPE_STORE, null)
            ->willReturn('0');

        $this->assertSame(2, $this->config->getMetaKeywordWeight());
    }

    public function testGetMetaKeywordWeightReturnsDefaultWhenNull(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_META_KEYWORD_WEIGHT, ScopeInterface::SCOPE_STORE, 2)
            ->willReturn(null);

        $this->assertSame(2, $this->config->getMetaKeywordWeight(2));
    }

    // ====================================================================
    // getSynonymGroups
    // ====================================================================

    public function testGetSynonymGroupsReturnsParsedGroups(): void
    {
        $raw = "bagageiro, suporte de carga, rack\nretrovisor, espelho";
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_SYNONYMS, ScopeInterface::SCOPE_STORE, null)
            ->willReturn($raw);

        $groups = $this->config->getSynonymGroups();

        $this->assertCount(2, $groups);
        $this->assertContains('bagageiro', $groups[0]);
        $this->assertContains('suporte de carga', $groups[0]);
        $this->assertContains('rack', $groups[0]);
        $this->assertContains('retrovisor', $groups[1]);
        $this->assertContains('espelho', $groups[1]);
    }

    public function testGetSynonymGroupsReturnsEmptyForBlankConfig(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_SYNONYMS, ScopeInterface::SCOPE_STORE, null)
            ->willReturn('');

        $this->assertSame([], $this->config->getSynonymGroups());
    }

    public function testGetSynonymGroupsReturnsEmptyForNullConfig(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_SYNONYMS, ScopeInterface::SCOPE_STORE, null)
            ->willReturn(null);

        $this->assertSame([], $this->config->getSynonymGroups());
    }

    public function testGetSynonymGroupsIgnoresSingleWordLines(): void
    {
        $raw = "bagageiro\nretrovisor, espelho";
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_SYNONYMS, ScopeInterface::SCOPE_STORE, null)
            ->willReturn($raw);

        $groups = $this->config->getSynonymGroups();

        $this->assertCount(1, $groups);
        $this->assertContains('retrovisor', $groups[0]);
        $this->assertContains('espelho', $groups[0]);
    }

    public function testGetSynonymGroupsDeduplicatesWithinLine(): void
    {
        $raw = "bagageiro, rack, bagageiro";
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_SYNONYMS, ScopeInterface::SCOPE_STORE, null)
            ->willReturn($raw);

        $groups = $this->config->getSynonymGroups();

        $this->assertCount(1, $groups);
        $this->assertCount(2, $groups[0]);
    }

    public function testGetSynonymGroupsHandlesWindowsLineEndings(): void
    {
        $raw = "bagageiro, rack\r\nretrovisor, espelho";
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_SYNONYMS, ScopeInterface::SCOPE_STORE, null)
            ->willReturn($raw);

        $groups = $this->config->getSynonymGroups();

        $this->assertCount(2, $groups);
    }

    public function testGetSynonymGroupsTrimsWhitespace(): void
    {
        $raw = "  bagageiro ,  rack  ";
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_SYNONYMS, ScopeInterface::SCOPE_STORE, null)
            ->willReturn($raw);

        $groups = $this->config->getSynonymGroups();

        $this->assertCount(1, $groups);
        $this->assertContains('bagageiro', $groups[0]);
        $this->assertContains('rack', $groups[0]);
    }

    public function testGetSynonymGroupsConvertsToLowercase(): void
    {
        $raw = "Bagageiro, RACK, Suporte";
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_SYNONYMS, ScopeInterface::SCOPE_STORE, null)
            ->willReturn($raw);

        $groups = $this->config->getSynonymGroups();

        $this->assertContains('bagageiro', $groups[0]);
        $this->assertContains('rack', $groups[0]);
        $this->assertContains('suporte', $groups[0]);
    }

    public function testGetSynonymGroupsIgnoresEmptyParts(): void
    {
        $raw = "bagageiro, , rack, ,";
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_SYNONYMS, ScopeInterface::SCOPE_STORE, null)
            ->willReturn($raw);

        $groups = $this->config->getSynonymGroups();

        $this->assertCount(1, $groups);
        $this->assertCount(2, $groups[0]);
    }

    public function testGetSynonymGroupsUsesStoreId(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_SYNONYMS, ScopeInterface::SCOPE_STORE, 3)
            ->willReturn('a, b');

        $groups = $this->config->getSynonymGroups(3);
        $this->assertCount(1, $groups);
    }
}
