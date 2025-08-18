<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Helper;

use Algolia\AlgoliaSearch\Helper\MathHelper;
use PHPUnit\Framework\TestCase;

class MathHelperTest extends TestCase
{

    /**
     * @dataProvider averageProvider
     */
    public function testAverage($values, $expectedResult)
    {
        $this->assertEquals($expectedResult, MathHelper::getAverage($values));
    }

    /**
     * @dataProvider standardDeviationProvider
     */
    public function testStandardDeviation($values, $expectedResult)
    {
        $this->assertEquals($expectedResult, MathHelper::getSampleStandardDeviation($values));
    }

    public static function averageProvider(): array
    {
        /** Tested with https://www.calculator.net/average-calculator.html */
        return [
            ['values' => [], 'expectedResult' => 0],
            ['values' => [1, 3], 'expectedResult' => 2],
            ['values' => ['foo' => 1, 'bar' => 3], 'expectedResult' => 2],
            ['values' => [1, 9], 'expectedResult' => 5],
            ['values' => [1, 2], 'expectedResult' => 1.5],
            ['values' => [1, 2, 3], 'expectedResult' => 2],
            ['values' => [1, 2, 4], 'expectedResult' => 2.33],
            ['values' => [11253, 10025, 9521, 13250], 'expectedResult' => 11012.25],
            ['values' => [10, 12, 23, 23, 16, 23, 21, 16], 'expectedResult' => 18],
        ];
    }

    public static function standardDeviationProvider(): array
    {
        /** Tested with https://www.calculator.net/standard-deviation-calculator.html */
        return [
            ['values' => [], 'expectedResult' => 0.0],
            ['values' => [1], 'expectedResult' => 0.0],
            ['values' => [1, 1], 'expectedResult' => 0.0],
            ['values' => [1, 3], 'expectedResult' => 1.41],
            ['values' => [1, 4, 12], 'expectedResult' => 5.69],
            ['values' => [3, 4, 6], 'expectedResult' => 1.53],
            ['values' => [3, 4, 6, 8, 7, 11], 'expectedResult' => 2.88],
            ['values' => [11253, 10025, 9521, 13250], 'expectedResult' => 1659.72],
            ['values' => [10, 12, 23, 23, 16, 23, 21, 16], 'expectedResult' => 5.24],
        ];
    }
}
