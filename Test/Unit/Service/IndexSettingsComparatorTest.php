<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\IndexSettingsComparator;
use Algolia\AlgoliaSearch\Test\TestCase;

class IndexSettingsComparatorTest extends TestCase
{
    protected ?AlgoliaConnector $connector = null;
    protected ?IndexOptionsInterface $indexOptions = null;

    protected IndexSettingsComparator $indexSettingsComparator;

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

        $this->indexSettingsComparator = new IndexSettingsComparator($this->connector);
    }

    public function testWithSameSettings(): void
    {
        $this->connector->expects($this->once())->method('getSettings')->willReturn($this->testSettings);
        // Must be the same
        $this->assertTrue($this->indexSettingsComparator->matches($this->indexOptions, $this->testSettings));
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
        $this->assertTrue($this->indexSettingsComparator->matches($this->indexOptions, $this->testSettings));
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
        $this->assertTrue($this->indexSettingsComparator->matches($this->indexOptions, $this->testSettings));
    }

    public function testWithAdditionalSettingsComingFromAlgolia(): void
    {
        $algoliaSettings = $this->testSettings;
        $algoliaSettings['additionalSettings'] = 'foo';

        $this->connector->expects($this->once())->method('getSettings')->willReturn($algoliaSettings);
        // Must be the same (additional settings are ignored by array_intersect_key)
        $this->assertTrue($this->indexSettingsComparator->matches($this->indexOptions, $this->testSettings));
    }

    public function testWithChangedValue(): void
    {
        $algoliaSettings = $this->testSettings;
        $algoliaSettings['maxValuesPerFacet'] = 10;

        $this->connector->expects($this->once())->method('getSettings')->willReturn($algoliaSettings);
        // Must be different
        $this->assertFalse($this->indexSettingsComparator->matches($this->indexOptions, $this->testSettings));
    }

    public function testWithChangedTyping(): void
    {
        $algoliaSettings = $this->testSettings;
        $algoliaSettings['typoTolerance'] = false;

        $this->connector->expects($this->once())->method('getSettings')->willReturn($algoliaSettings);
        // Must be different
        $this->assertFalse($this->indexSettingsComparator->matches($this->indexOptions, $this->testSettings));
    }

    public function testWithMissingValue(): void
    {
        $algoliaSettings = $this->testSettings;
        unset($algoliaSettings['removeWordsIfNoResults']);

        $this->connector->expects($this->once())->method('getSettings')->willReturn($algoliaSettings);
        // Must be different
        $this->assertFalse($this->indexSettingsComparator->matches($this->indexOptions, $this->testSettings));
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
        $this->assertFalse($this->indexSettingsComparator->matches($this->indexOptions, $this->testSettings));
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
        $this->assertFalse($this->indexSettingsComparator->matches($this->indexOptions, $this->testSettings));
    }

    public function testInvalidJson(): void
    {
        $algoliaSettings = [INF];

        $this->connector->expects($this->once())->method('getSettings')->willReturn($algoliaSettings);

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessageMatches('/Invalid JSON/');
        // Must be different
        $this->assertFalse($this->indexSettingsComparator->matches($this->indexOptions, [INF]));
    }

    // ── reconcileKeys() ──

    /**
     * @dataProvider reconcileKeysProvider
     */
    public function testReconcileKeys(array $remote, array $local, array $expectedRemote, array $expectedLocal): void
    {
        [$reconciledRemote, $reconciledLocal] = $this->invokeMethod(
            $this->indexSettingsComparator,
            'reconcileKeys',
            [$remote, $local]
        );

        $this->assertSame($expectedRemote, $reconciledRemote);
        $this->assertSame($expectedLocal, $reconciledLocal);
    }

    public static function reconcileKeysProvider(): array
    {
        return [
            'remote-only keys are dropped' => [
                'remote' => ['searchableAttributes' => ['name'], 'algoliaManagedSetting' => 'foo'],
                'local' => ['searchableAttributes' => ['name']],
                'expectedRemote' => ['searchableAttributes' => ['name']],
                'expectedLocal' => ['searchableAttributes' => ['name']],
            ],
            'empty local customRanking absent remotely is dropped from local' => [
                'remote' => ['searchableAttributes' => ['name']],
                'local' => ['searchableAttributes' => ['name'], 'customRanking' => []],
                'expectedRemote' => ['searchableAttributes' => ['name']],
                'expectedLocal' => ['searchableAttributes' => ['name']],
            ],
            'empty local unretrievableAttributes absent remotely is dropped from local' => [
                'remote' => ['searchableAttributes' => ['name']],
                'local' => ['searchableAttributes' => ['name'], 'unretrievableAttributes' => []],
                'expectedRemote' => ['searchableAttributes' => ['name']],
                'expectedLocal' => ['searchableAttributes' => ['name']],
            ],
            'empty local customRanking with null remote value is coerced to null' => [
                'remote' => ['customRanking' => null],
                'local' => ['customRanking' => []],
                'expectedRemote' => ['customRanking' => null],
                'expectedLocal' => ['customRanking' => null],
            ],
            'empty local unretrievableAttributes with null remote value is coerced to null' => [
                'remote' => ['unretrievableAttributes' => null],
                'local' => ['unretrievableAttributes' => []],
                'expectedRemote' => ['unretrievableAttributes' => null],
                'expectedLocal' => ['unretrievableAttributes' => null],
            ],
            'empty local value with non-null empty remote value is left untouched' => [
                'remote' => ['customRanking' => []],
                'local' => ['customRanking' => []],
                'expectedRemote' => ['customRanking' => []],
                'expectedLocal' => ['customRanking' => []],
            ],
            'empty local value with real remote value is left untouched (surfaces as a diff)' => [
                'remote' => ['customRanking' => ['desc(price)']],
                'local' => ['customRanking' => []],
                'expectedRemote' => ['customRanking' => ['desc(price)']],
                'expectedLocal' => ['customRanking' => []],
            ],
            'non-empty local value absent remotely is left untouched (surfaces as a diff)' => [
                'remote' => [],
                'local' => ['customRanking' => ['desc(price)']],
                'expectedRemote' => [],
                'expectedLocal' => ['customRanking' => ['desc(price)']],
            ],
        ];
    }

    // ── customRanking / unretrievableAttributes round-trip omission ──

    public function testMatchesWhenCustomRankingEmptyLocallyAndAbsentFromRemote(): void
    {
        $local = ['searchableAttributes' => ['name'], 'customRanking' => []];
        // Algolia omits customRanking entirely from getSettings() whenever it's empty.
        $remote = ['searchableAttributes' => ['name']];

        $this->connector->expects($this->once())->method('getSettings')->willReturn($remote);

        $this->assertTrue($this->indexSettingsComparator->matches($this->indexOptions, $local));
    }

    public function testMatchesWhenUnretrievableAttributesEmptyLocallyAndAbsentFromRemote(): void
    {
        $local = ['searchableAttributes' => ['name'], 'unretrievableAttributes' => []];
        // Algolia omits unretrievableAttributes until it has been set at least once.
        $remote = ['searchableAttributes' => ['name']];

        $this->connector->expects($this->once())->method('getSettings')->willReturn($remote);

        $this->assertTrue($this->indexSettingsComparator->matches($this->indexOptions, $local));
    }

    public function testMatchesWhenCustomRankingEmptyLocallyAndNullRemotely(): void
    {
        $local = ['customRanking' => []];
        $remote = ['customRanking' => null];

        $this->connector->expects($this->once())->method('getSettings')->willReturn($remote);

        $this->assertTrue($this->indexSettingsComparator->matches($this->indexOptions, $local));
    }

    public function testMatchesWhenUnretrievableAttributesEmptyLocallyAndNullRemotely(): void
    {
        $local = ['unretrievableAttributes' => []];
        $remote = ['unretrievableAttributes' => null];

        $this->connector->expects($this->once())->method('getSettings')->willReturn($remote);

        $this->assertTrue($this->indexSettingsComparator->matches($this->indexOptions, $local));
    }

    public function testMatchesWhenCustomRankingAndUnretrievableAttributesBothEmptyAndAbsentRemotely(): void
    {
        $local = [
            'searchableAttributes' => ['name'],
            'customRanking' => [],
            'unretrievableAttributes' => [],
        ];
        $remote = ['searchableAttributes' => ['name']];

        $this->connector->expects($this->once())->method('getSettings')->willReturn($remote);

        $this->assertTrue($this->indexSettingsComparator->matches($this->indexOptions, $local));
    }

    public function testDoesNotMatchWhenCustomRankingNonEmptyLocallyButAbsentFromRemote(): void
    {
        $local = ['customRanking' => ['desc(price)']];
        // Not an omitted-because-empty case: the extension proposes a real ranking Algolia doesn't have.
        $remote = [];

        $this->connector->expects($this->once())->method('getSettings')->willReturn($remote);

        $this->assertFalse($this->indexSettingsComparator->matches($this->indexOptions, $local));
    }

    public function testDoesNotMatchWhenUnretrievableAttributesDifferAndBothNonEmpty(): void
    {
        $local = ['unretrievableAttributes' => ['in_stock']];
        $remote = ['unretrievableAttributes' => ['ordered_qty']];

        $this->connector->expects($this->once())->method('getSettings')->willReturn($remote);

        $this->assertFalse($this->indexSettingsComparator->matches($this->indexOptions, $local));
    }

    public function testUsesProvidedRemoteSettingsWhenPassed(): void
    {
        // When remote settings are supplied, the connector must not be queried.
        $this->connector->expects($this->never())->method('getSettings');

        $this->assertTrue(
            $this->indexSettingsComparator->matches($this->indexOptions, $this->testSettings, $this->testSettings)
        );

        $changedRemote = $this->testSettings;
        $changedRemote['maxValuesPerFacet'] = 10;

        $this->assertFalse(
            $this->indexSettingsComparator->matches($this->indexOptions, $this->testSettings, $changedRemote)
        );
    }
}
