<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Index\Settings;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\Index\Settings\IndexSettingsComparator;
use Algolia\AlgoliaSearch\Test\TestCase;

class IndexSettingsComparatorTest extends TestCase
{
    protected array $testSettings = [
        'searchableAttributes' => [
            'unordered(name)',
            'unordered(sku)',
            'unordered(manufacturer)',
            'unordered(categories)',
            'unordered(categories_without_path)',
            'unordered(color)',
        ],
        'customRanking' => [
            'desc(in_stock)',
            'desc(ordered_qty)',
            'desc(created_at)',
        ],
        'unretrievableAttributes' => [
            'in_stock',
            'ordered_qty',
        ],
        'attributesForFaceting' => [
            'price.USD.default',
            'categories',
            'searchable(color)',
            'searchable(activity)',
            'categories.level0',
            'categoryIds',
        ],
        'maxValuesPerFacet' => 5,
        'removeWordsIfNoResults' => 'allOptional',
        'typoTolerance' => 'false',
        'dummyAttribute' => [
            'foo' => 'bar',
            'bar' => 'foo',
            'baz' => 'foo',
        ],
    ];

    protected function createObjectToTest(?AlgoliaConnector $connector = null): IndexSettingsComparator
    {
        return new IndexSettingsComparator($connector ?? $this->createStub(AlgoliaConnector::class));
    }

    public function testWithSameSettings(): void
    {
        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->expects($this->once())->method('getSettings')->willReturn($this->testSettings);

        $indexSettingsComparator = $this->createObjectToTest($connector);

        // Must be the same
        $this->assertTrue($indexSettingsComparator->matches($this->createStub(IndexOptionsInterface::class), $this->testSettings));
    }

    public function testWithSameSettingsButOrderedDifferently(): void
    {
        $algoliaSettings = [
            'maxValuesPerFacet' => 5,
            'removeWordsIfNoResults' => 'allOptional',
            'typoTolerance' => 'false',
            'dummyAttribute' => [
                'foo' => 'bar',
                'bar' => 'foo',
                'baz' => 'foo',
            ],
            'searchableAttributes' => [
                'unordered(name)',
                'unordered(sku)',
                'unordered(manufacturer)',
                'unordered(categories)',
                'unordered(categories_without_path)',
                'unordered(color)',
            ],
            'unretrievableAttributes' => [
                'in_stock',
                'ordered_qty',
            ],
            'attributesForFaceting' => [
                'price.USD.default',
                'categories',
                'searchable(color)',
                'searchable(activity)',
                'categories.level0',
                'categoryIds',
            ],
            'customRanking' => [
                'desc(in_stock)',
                'desc(ordered_qty)',
                'desc(created_at)',
            ],
        ];

        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->expects($this->once())->method('getSettings')->willReturn($algoliaSettings);

        $indexSettingsComparator = $this->createObjectToTest($connector);

        // Must be the same (attributes are re-ordered by ksort)
        $this->assertTrue($indexSettingsComparator->matches($this->createStub(IndexOptionsInterface::class), $this->testSettings));
    }

    public function testWithSameSettingsButWithMixedAssociativeArray(): void
    {
        $algoliaSettings = $this->testSettings;
        $algoliaSettings['dummyAttribute'] = [
            'baz' => 'foo',
            'foo' => 'bar',
            'bar' => 'foo',
        ];

        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->expects($this->once())->method('getSettings')->willReturn($algoliaSettings);

        $indexSettingsComparator = $this->createObjectToTest($connector);

        // Must be the same (associative arrays are re-ordered by recursive ksort)
        $this->assertTrue($indexSettingsComparator->matches($this->createStub(IndexOptionsInterface::class), $this->testSettings));
    }

    public function testWithAdditionalSettingsComingFromAlgolia(): void
    {
        $algoliaSettings = $this->testSettings;
        $algoliaSettings['additionalSettings'] = 'foo';

        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->expects($this->once())->method('getSettings')->willReturn($algoliaSettings);

        $indexSettingsComparator = $this->createObjectToTest($connector);

        // Must be the same (additional settings are ignored by array_intersect_key)
        $this->assertTrue($indexSettingsComparator->matches($this->createStub(IndexOptionsInterface::class), $this->testSettings));
    }

    public function testWithChangedValue(): void
    {
        $algoliaSettings = $this->testSettings;
        $algoliaSettings['maxValuesPerFacet'] = 10;

        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->expects($this->once())->method('getSettings')->willReturn($algoliaSettings);

        $indexSettingsComparator = $this->createObjectToTest($connector);

        // Must be different
        $this->assertFalse($indexSettingsComparator->matches($this->createStub(IndexOptionsInterface::class), $this->testSettings));
    }

    public function testWithChangedTyping(): void
    {
        $algoliaSettings = $this->testSettings;
        $algoliaSettings['typoTolerance'] = false;

        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->expects($this->once())->method('getSettings')->willReturn($algoliaSettings);

        $indexSettingsComparator = $this->createObjectToTest($connector);

        // Must be different
        $this->assertFalse($indexSettingsComparator->matches($this->createStub(IndexOptionsInterface::class), $this->testSettings));
    }

    public function testWithMissingValue(): void
    {
        $algoliaSettings = $this->testSettings;
        unset($algoliaSettings['removeWordsIfNoResults']);

        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->expects($this->once())->method('getSettings')->willReturn($algoliaSettings);

        $indexSettingsComparator = $this->createObjectToTest($connector);

        // Must be different
        $this->assertFalse($indexSettingsComparator->matches($this->createStub(IndexOptionsInterface::class), $this->testSettings));
    }

    public function testWithRemovedOrderingAttribute(): void
    {
        $algoliaSettings = $this->testSettings;
        $algoliaSettings['searchableAttributes'] = [
            'unordered(name)',
            'unordered(sku)',
            'unordered(manufacturer)',
            'unordered(categories)',
            'unordered(categories_without_path)',
            // removed color
        ];

        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->expects($this->once())->method('getSettings')->willReturn($algoliaSettings);

        $indexSettingsComparator = $this->createObjectToTest($connector);

        // Must be different
        $this->assertFalse($indexSettingsComparator->matches($this->createStub(IndexOptionsInterface::class), $this->testSettings));
    }

    public function testWithChangedOrdering(): void
    {
        $algoliaSettings = $this->testSettings;
        $algoliaSettings['searchableAttributes'] = [
            'unordered(name)',
            'unordered(name)',
            'unordered(sku)',
            'unordered(color)', // moved color
            'unordered(manufacturer)',
            'unordered(categories)',
            'unordered(categories_without_path)',
        ];

        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->expects($this->once())->method('getSettings')->willReturn($algoliaSettings);

        $indexSettingsComparator = $this->createObjectToTest($connector);

        // Must be different
        $this->assertFalse($indexSettingsComparator->matches($this->createStub(IndexOptionsInterface::class), $this->testSettings));
    }

    public function testInvalidJson(): void
    {
        $algoliaSettings = [INF];

        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->expects($this->once())->method('getSettings')->willReturn($algoliaSettings);

        $indexSettingsComparator = $this->createObjectToTest($connector);

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessageMatches('/Invalid JSON/');
        // Must be different
        $this->assertFalse($indexSettingsComparator->matches($this->createStub(IndexOptionsInterface::class), [INF]));
    }

    public function testUsesProvidedRemoteSettingsWhenPassed(): void
    {
        // When remote settings are supplied, the connector must not be queried.
        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->expects($this->never())->method('getSettings');

        $indexSettingsComparator = $this->createObjectToTest($connector);
        $indexOptions = $this->createStub(IndexOptionsInterface::class);

        $this->assertTrue(
            $indexSettingsComparator->matches($indexOptions, $this->testSettings, $this->testSettings)
        );

        $changedRemote = $this->testSettings;
        $changedRemote['maxValuesPerFacet'] = 10;

        $this->assertFalse(
            $indexSettingsComparator->matches($indexOptions, $this->testSettings, $changedRemote)
        );
    }
}
