<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Helper;

use Algolia\AlgoliaSearch\Helper\ArrayDeduplicator;
use PHPUnit\Framework\TestCase;

class ArrayDeduplicatorTest extends TestCase
{
    protected ?ArrayDeduplicator $deduplicator = null;

    protected function setUp(): void
    {
        $this->deduplicator = new ArrayDeduplicator();
    }

    public static function dedupeArrayOfArraysProvider(): array
    {
        return [
            'empty array' => [
                'input' => [],
                'expectedCount' => 0,
                'expectedItems' => []
            ],
            'no duplicates' => [
                'input' => [
                    ['a' => 1, 'b' => 2],
                    ['a' => 2, 'b' => 3],
                    ['a' => 3, 'b' => 4]
                ],
                'expectedCount' => 3,
                'expectedItems' => [
                    ['a' => 1, 'b' => 2],
                    ['a' => 2, 'b' => 3],
                    ['a' => 3, 'b' => 4]
                ]
            ],
            'exact duplicates' => [
                'input' => [
                    ['a' => 1, 'b' => 2],
                    ['a' => 1, 'b' => 2], // duplicate
                    ['a' => 2, 'b' => 3]
                ],
                'expectedCount' => 2,
                'expectedItems' => [
                    ['a' => 1, 'b' => 2],
                    ['a' => 2, 'b' => 3]
                ]
            ],
            'multiple duplicates' => [
                'input' => [
                    ['id' => 1, 'name' => 'test'],
                    ['id' => 2, 'name' => 'test2'],
                    ['id' => 1, 'name' => 'test'], // duplicate
                    ['id' => 3, 'name' => 'test3'],
                    ['id' => 2, 'name' => 'test2'] // duplicate
                ],
                'expectedCount' => 3,
                'expectedItems' => [
                    ['id' => 1, 'name' => 'test'],
                    ['id' => 2, 'name' => 'test2'],
                    ['id' => 3, 'name' => 'test3']
                ]
            ],
            'duplicate synonyms' => [
                'input' => [
                    ['gray', 'grey'],
                    ['trousers', 'pants'],
                    ['ipad', 'tablet'],
                    ['caulk', 'caulking'],
                    ['trousers', 'pants'], // duplicate
                    ['molding', 'moldings', 'moulding', 'mouldings'],
                    ['trash', 'garbage'],
                    ['molding', 'moldings', 'moulding', 'mouldings'], // duplicate
                ],
                'expectedCount' => 6,
                'expectedItems' => [
                    ['gray', 'grey'],
                    ['trousers', 'pants'],
                    ['ipad', 'tablet'],
                    ['caulk', 'caulking'],
                    ['molding', 'moldings', 'moulding', 'mouldings'],
                    ['trash', 'garbage'],
                ]
            ],
            'duplicate alt corrections' => [
                'input' => [
                    [ 'word' => 'trousers', 'nbTypos' => 1, 'correction' => 'pants' ],
                    [ 'word' => 'rod', 'nbTypos' => 1, 'correction' => 'bar' ],
                    [ 'word' => 'bell', 'nbTypos' => 1, 'correction' => 'buzzer' ],
                    [ 'word' => 'rod', 'nbTypos' => 1, 'correction' => 'bar' ], // duplicate
                    [ 'word' => 'blind', 'nbTypos' => 1, 'correction' => 'shade' ],
                    [ 'word' => 'blind', 'nbTypos' => 2, 'correction' => 'shade' ], // not a duplicate
                    [ 'word' => 'trousers', 'nbTypos' => 1, 'correction' => 'pants' ], // duplicate
                ],
                'expectedCount' => 5,
                'expectedItems' => [
                    [ 'word' => 'trousers', 'nbTypos' => 1, 'correction' => 'pants' ],
                    [ 'word' => 'rod', 'nbTypos' => 1, 'correction' => 'bar' ],
                    [ 'word' => 'bell', 'nbTypos' => 1, 'correction' => 'buzzer' ],
                    [ 'word' => 'blind', 'nbTypos' => 1, 'correction' => 'shade' ],
                    [ 'word' => 'blind', 'nbTypos' => 2, 'correction' => 'shade' ],
                ]
            ]
        ];
    }

    /**
     * @dataProvider dedupeArrayOfArraysProvider
     */
    public function testDedupeArrayOfArrays(array $input, int $expectedCount, array $expectedItems): void
    {
        $result = $this->deduplicator->dedupeArrayOfArrays($input);

        $this->assertCount($expectedCount, $result);

        foreach ($expectedItems as $expectedItem) {
            $this->assertContains($expectedItem, $result);
        }
    }

    public function testDedupeArrayOfArraysKeepsOrderOfFirstOccurrences(): void
    {
        $data = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 1], // duplicate
        ];

        $result = $this->deduplicator->dedupeArrayOfArrays($data);

        $this->assertSame([['id' => 1], ['id' => 2]], $result);
    }

    public function testDedupeSpecificSettingsOnlyProcessesRequestedSettings(): void
    {
        $settings = [
            'synonyms' => [
                ['red' => 'rouge'],
                ['red' => 'rouge'],
            ],
            'altCorrections' => [
                [ 'word' => 'bell', 'nbTypos' => 1, 'correction' => 'buzzer' ],
            ],
            'placeholder' => [
                ['foo' => 'bar'],
            ],
        ];

        $result = $this->deduplicator->dedupeSpecificSettings(
            ['synonyms', 'altCorrections'],
            $settings
        );

        $this->assertArrayHasKey('synonyms', $result);
        $this->assertCount(1, $result['synonyms']); // deduped
        $this->assertArrayHasKey('altCorrections', $result);
        $this->assertCount(1, $result['altCorrections']);
        $this->assertArrayNotHasKey('placeholders', $result);
    }

    public function testDedupeSpecificSettingsHandlesMissingKeys(): void
    {
        $settings = [
            'synonyms' => [['red', 'rouge']],
        ];

        $result = $this->deduplicator->dedupeSpecificSettings(
            ['synonyms', 'altCorrections'],
            $settings
        );

        $this->assertArrayHasKey('synonyms', $result);
        $this->assertArrayNotHasKey('altCorrections', $result);
    }
}
