<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Index\Settings;

use Algolia\AlgoliaSearch\Logger\AlgoliaLogger;
use Algolia\AlgoliaSearch\Service\Index\Settings\IndexSettingsPreserver;
use Algolia\AlgoliaSearch\Test\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class IndexSettingsPreserverTest extends TestCase
{
    protected ?AlgoliaLogger $logger = null;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(AlgoliaLogger::class);
    }

    public function testPreservesUnderscoreEntryAbsentFromProposed(): void
    {
        $this->logger->expects($this->never())->method('warning');
        $preserver = new IndexSettingsPreserver($this->logger);

        $proposed = ['attributesForFaceting' => ['categories', 'searchable(color)']];
        $remote = ['attributesForFaceting' => ['categories', 'searchable(color)', '_collections']];

        $result = $preserver->preserve($proposed, $remote);

        $this->assertContains('_collections', $result['attributesForFaceting']);
        $this->assertContains('categories', $result['attributesForFaceting']);
        $this->assertContains('searchable(color)', $result['attributesForFaceting']);
    }

    public function testPreservesDecoratedUnderscoreEntry(): void
    {
        $this->logger->expects($this->never())->method('warning');
        $preserver = new IndexSettingsPreserver($this->logger);

        $proposed = ['attributesForFaceting' => ['categories']];
        $remote = ['attributesForFaceting' => ['categories', 'filterOnly(_collections)']];

        $result = $preserver->preserve($proposed, $remote);

        // The decorated entry must be preserved verbatim.
        $this->assertContains('filterOnly(_collections)', $result['attributesForFaceting']);
    }

    public function testReturnsProposedUnchangedWhenNoApplicableKey(): void
    {
        $this->logger->expects($this->never())->method('warning');
        $preserver = new IndexSettingsPreserver($this->logger);

        $proposed = ['replicas' => ['magento2_default_products_price_asc']];
        $remote = ['attributesForFaceting' => ['_collections']];

        $result = $preserver->preserve($proposed, $remote);

        $this->assertSame($proposed, $result);
    }

    public function testRemoteWinsAndLogsWhenProposedContainsProtectedEntry(): void
    {
        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('_foo'));
        $preserver = new IndexSettingsPreserver($this->logger);

        $proposed = ['attributesForFaceting' => ['categories', '_foo']];
        $remote = ['attributesForFaceting' => ['_foo']];

        $result = $preserver->preserve($proposed, $remote);

        $this->assertContains('categories', $result['attributesForFaceting']);
        $this->assertContains('_foo', $result['attributesForFaceting']);
        // Remote wins: the entry appears exactly once.
        $this->assertSame(1, array_count_values($result['attributesForFaceting'])['_foo']);
    }

    public function testEmptyRemoteReturnsProposedUnchanged(): void
    {
        $this->logger->expects($this->never())->method('warning');
        $preserver = new IndexSettingsPreserver($this->logger);

        $proposed = ['attributesForFaceting' => ['categories', 'searchable(color)']];
        $remote = [];

        $result = $preserver->preserve($proposed, $remote);

        $this->assertSame($proposed, $result);
    }

    public function testCustomRulesArgumentOverridesDefaults(): void
    {
        $this->logger->expects($this->never())->method('warning');
        $preserver = new IndexSettingsPreserver($this->logger, ['searchableAttributes' => '/^algolia_/']);

        $proposed = [
            'searchableAttributes' => ['name'],
            // The default attributesForFaceting rule must NOT apply when custom rules are supplied.
            'attributesForFaceting' => ['categories'],
        ];
        $remote = [
            'searchableAttributes' => ['name', 'algolia_internal'],
            'attributesForFaceting' => ['categories', '_collections'],
        ];

        $result = $preserver->preserve($proposed, $remote);

        $this->assertContains('algolia_internal', $result['searchableAttributes']);
        $this->assertNotContains('_collections', $result['attributesForFaceting']);
    }

    public function testDeduplicatesWhenRemotePreservedEntryAlreadyInProposed(): void
    {
        $preserver = new IndexSettingsPreserver($this->logger);

        $proposed = ['attributesForFaceting' => ['categories', 'filterOnly(_collections)']];
        $remote = ['attributesForFaceting' => ['filterOnly(_collections)']];

        $result = $preserver->preserve($proposed, $remote);

        $this->assertSame(1, array_count_values($result['attributesForFaceting'])['filterOnly(_collections)']);
    }
}
