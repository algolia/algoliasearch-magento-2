<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\IndexSettingsDiffChecker;
use Algolia\AlgoliaSearch\Test\TestCase;

class IndexSettingsDiffCheckerTest extends TestCase
{
    protected ?AlgoliaConnector $connector = null;
    protected ?IndexOptionsInterface $indexOptions = null;

    protected IndexSettingsDiffChecker $indexSettingsDiffChecker;

    protected array $testSettings = [
        "searchableAttributes" => [
            "unordered(name)",
            "unordered(sku)",
            "unordered(manufacturer)",
            "unordered(categories)",
            "unordered(categories_without_path)",
            "unordered(color)"
        ],
        "customRanking" => [
            "desc(in_stock)",
            "desc(ordered_qty)",
            "desc(created_at)",
        ],
        "unretrievableAttributes" => [
            "in_stock",
            "ordered_qty",
        ],
        "attributesForFaceting" => [
            "price.USD.default",
            "categories",
            "searchable(color)",
            "searchable(activity)",
            "categories.level0",
            "categoryIds",
        ],
        "maxValuesPerFacet" => 5,
        "removeWordsIfNoResults" => "allOptional",
        "typoTolerance" => "false",
        "dummyAttribute" => [
            "foo" => "bar",
            "bar" => "foo",
            "baz" => "foo",
        ]
    ];

    protected function setUp(): void
    {
        $this->connector = $this->createMock(AlgoliaConnector::class);
        $this->indexOptions = $this->createMock(IndexOptionsInterface::class);

        $this->indexSettingsDiffChecker = new IndexSettingsDiffChecker($this->connector);
    }

    public function testWithSameSettings(): void
    {
        $this->connector->expects($this->once())->method('getSettings')->willReturn($this->testSettings);
        // Must be the same
        $this->assertTrue($this->indexSettingsDiffChecker->matchAlgoliaSettings($this->indexOptions, $this->testSettings));
    }

    public function testWithSameSettingsButOrderedDifferently(): void
    {
        $algoliaSettings = [
            "maxValuesPerFacet" => 5,
            "removeWordsIfNoResults" => "allOptional",
            "typoTolerance" => "false",
            "dummyAttribute" => [
                "foo" => "bar",
                "bar" => "foo",
                "baz" => "foo",
            ],
            "searchableAttributes" => [
                "unordered(name)",
                "unordered(sku)",
                "unordered(manufacturer)",
                "unordered(categories)",
                "unordered(categories_without_path)",
                "unordered(color)"
            ],
            "unretrievableAttributes" => [
                "in_stock",
                "ordered_qty",
            ],
            "attributesForFaceting" => [
                "price.USD.default",
                "categories",
                "searchable(color)",
                "searchable(activity)",
                "categories.level0",
                "categoryIds",
            ],
            "customRanking" => [
                "desc(in_stock)",
                "desc(ordered_qty)",
                "desc(created_at)",
            ],
        ];

        $this->connector->expects($this->once())->method('getSettings')->willReturn($algoliaSettings);
        // Must be the same (attributes are re-ordered by ksort)
        $this->assertTrue($this->indexSettingsDiffChecker->matchAlgoliaSettings($this->indexOptions, $this->testSettings));
    }

    public function testWithSameSettingsButWithMixedAssociativeArray(): void
    {
        $algoliaSettings = $this->testSettings;
        $algoliaSettings['dummyAttribute'] = [
            "baz" => "foo",
            "foo" => "bar",
            "bar" => "foo",
        ];

        $this->connector->expects($this->once())->method('getSettings')->willReturn($algoliaSettings);
        // Must be the same (associative arrays are re-ordered by recursive ksort)
        $this->assertTrue($this->indexSettingsDiffChecker->matchAlgoliaSettings($this->indexOptions, $this->testSettings));
    }

    public function testWithAdditionalSettingsComingFromAlgolia(): void
    {
        $algoliaSettings = $this->testSettings;
        $algoliaSettings['additionalSettings'] = 'foo';

        $this->connector->expects($this->once())->method('getSettings')->willReturn($algoliaSettings);
        // Must be the same (additional settings are ignored by array_intersect_key)
        $this->assertTrue($this->indexSettingsDiffChecker->matchAlgoliaSettings($this->indexOptions, $this->testSettings));
    }

    public function testWithChangedValue(): void
    {
        $algoliaSettings = $this->testSettings;
        $algoliaSettings['maxValuesPerFacet'] = 10;

        $this->connector->expects($this->once())->method('getSettings')->willReturn($algoliaSettings);
        // Must be different
        $this->assertFalse($this->indexSettingsDiffChecker->matchAlgoliaSettings($this->indexOptions, $this->testSettings));
    }

    public function testWithChangedTyping(): void
    {
        $algoliaSettings = $this->testSettings;
        $algoliaSettings['typoTolerance'] = false;

        $this->connector->expects($this->once())->method('getSettings')->willReturn($algoliaSettings);
        // Must be different
        $this->assertFalse($this->indexSettingsDiffChecker->matchAlgoliaSettings($this->indexOptions, $this->testSettings));
    }

    public function testWithMissingValue(): void
    {
        $algoliaSettings = $this->testSettings;
        unset($algoliaSettings['removeWordsIfNoResults']);

        $this->connector->expects($this->once())->method('getSettings')->willReturn($algoliaSettings);
        // Must be different
        $this->assertFalse($this->indexSettingsDiffChecker->matchAlgoliaSettings($this->indexOptions, $this->testSettings));
    }

    public function testWithRemovedOrderingAttribute(): void
    {
        $algoliaSettings = $this->testSettings;
        $algoliaSettings['searchableAttributes'] = [
            "unordered(name)",
            "unordered(sku)",
            "unordered(manufacturer)",
            "unordered(categories)",
            "unordered(categories_without_path)"
            // removed color
        ];

        $this->connector->expects($this->once())->method('getSettings')->willReturn($algoliaSettings);
        // Must be different
        $this->assertFalse($this->indexSettingsDiffChecker->matchAlgoliaSettings($this->indexOptions, $this->testSettings));
    }

    public function testWithChangedOrdering(): void
    {
        $algoliaSettings = $this->testSettings;
        $algoliaSettings['searchableAttributes'] = [
            "unordered(name)",
            "unordered(name)",
            "unordered(sku)",
            "unordered(color)", // moved color
            "unordered(manufacturer)",
            "unordered(categories)",
            "unordered(categories_without_path)"
        ];

        $this->connector->expects($this->once())->method('getSettings')->willReturn($algoliaSettings);
        // Must be different
        $this->assertFalse($this->indexSettingsDiffChecker->matchAlgoliaSettings($this->indexOptions, $this->testSettings));
    }
}
