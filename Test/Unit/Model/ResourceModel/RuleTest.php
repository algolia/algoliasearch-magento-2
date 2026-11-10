<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Model\ResourceModel;

use Algolia\AlgoliaSearch\Model\ResourceModel\Rule;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\Attributes\DataProvider;

class RuleTest extends TestCase
{
    protected function createObjectToTest(): Rule
    {
        return $this->getMockBuilder(Rule::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getTable'])
            ->getMock();
    }

    public function testGetAllRulePricesFromProductBuildsCorrectQueryAndReturnsResults(): void
    {
        $date = new \DateTime('2026-10-13');
        $websiteId = 1;
        $productIds = [10, 20, 30];
        $expectedResults = [
            ['product_id' => 10, 'customer_group_id' => 0, 'rule_price' => 99.99],
            ['product_id' => 20, 'customer_group_id' => 1, 'rule_price' => 89.99],
        ];

        $select = $this->createMock(Select::class);
        $select->expects($this->once())
            ->method('from')
            ->with('catalogrule_product_price_table')
            ->willReturnSelf();
        $select->expects($this->exactly(3))
            ->method('where')
            ->with(
                $this->logicalOr(
                    $this->identicalTo('rule_date = ?'),
                    $this->identicalTo('website_id = ?'),
                    $this->identicalTo('product_id IN(?)')
                ),
                $this->anything(),
                $this->anything()
            )
            ->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())
            ->method('select')
            ->willReturn($select);
        $connection->expects($this->once())
            ->method('fetchAll')
            ->with($select)
            ->willReturn($expectedResults);

        $rule = $this->createObjectToTest();
        $rule->expects($this->once())
            ->method('getConnection')
            ->willReturn($connection);
        $rule->expects($this->once())
            ->method('getTable')
            ->with('catalogrule_product_price')
            ->willReturn('catalogrule_product_price_table');

        $result = $rule->getAllRulePricesFromProduct($date, $websiteId, $productIds);

        $this->assertSame($expectedResults, $result);
    }

    /**
     * @return array<string, array{0: array<int>, 1: array<array<string, mixed>>}>
     */
    public static function rulePricesDataProvider(): array
    {
        return [
            'empty result' => [
                [100],
                [],
            ],
            'single product with one price' => [
                [42],
                [['product_id' => 42, 'customer_group_id' => 0, 'rule_price' => 49.99]],
            ],
            'multiple products' => [
                [10, 20, 30],
                [
                    ['product_id' => 10, 'customer_group_id' => 0, 'rule_price' => 99.99],
                    ['product_id' => 20, 'customer_group_id' => 1, 'rule_price' => 89.99],
                ],
            ],
        ];
    }

    #[DataProvider('rulePricesDataProvider')]
    public function testGetAllRulePricesFromProductReturnsExpectedResults(
        array $productIds,
        array $expectedResults
    ): void {
        $date = new \DateTime('2026-10-13');
        $websiteId = 1;

        $select = $this->createMock(Select::class);
        $select->expects($this->once())
            ->method('from')
            ->with($this->anything())
            ->willReturnSelf();
        $select->expects($this->exactly(3))
            ->method('where')
            ->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())
            ->method('select')
            ->willReturn($select);
        $connection->expects($this->once())
            ->method('fetchAll')
            ->with($select)
            ->willReturn($expectedResults);

        $rule = $this->createObjectToTest();
        $rule->expects($this->once())
            ->method('getConnection')
            ->willReturn($connection);
        $rule->expects($this->once())
            ->method('getTable')
            ->with('catalogrule_product_price')
            ->willReturn('catalogrule_product_price_table');

        $result = $rule->getAllRulePricesFromProduct($date, $websiteId, $productIds);

        $this->assertSame($expectedResults, $result);
    }

    public function testGetAllRulePricesFromProductFormatsDateCorrectly(): void
    {
        $date = new \DateTime('2026-12-25 15:30:45');
        $websiteId = 1;
        $productIds = [10];

        $select = $this->createMock(Select::class);
        $select->expects($this->once())
            ->method('from')
            ->willReturnSelf();
        $select->expects($this->exactly(3))
            ->method('where')
            ->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())
            ->method('select')
            ->willReturn($select);
        $connection->expects($this->once())
            ->method('fetchAll')
            ->with($select)
            ->willReturn([]);

        $rule = $this->createObjectToTest();
        $rule->expects($this->once())
            ->method('getConnection')
            ->willReturn($connection);
        $rule->expects($this->once())
            ->method('getTable')
            ->with('catalogrule_product_price')
            ->willReturn('catalogrule_product_price_table');

        $result = $rule->getAllRulePricesFromProduct($date, $websiteId, $productIds);

        $this->assertSame([], $result);
    }

    public function testGetAllRulePricesFromProductUsesCorrectTableName(): void
    {
        $date = new \DateTime('2026-10-13');
        $websiteId = 1;
        $productIds = [10];

        $select = $this->createMock(Select::class);
        $select->expects($this->once())
            ->method('from')
            ->with('prefix_catalogrule_product_price')
            ->willReturnSelf();
        $select->expects($this->exactly(3))
            ->method('where')
            ->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())
            ->method('select')
            ->willReturn($select);
        $connection->expects($this->once())
            ->method('fetchAll')
            ->with($select)
            ->willReturn([]);

        $rule = $this->createObjectToTest();
        $rule->expects($this->once())
            ->method('getConnection')
            ->willReturn($connection);
        $rule->expects($this->once())
            ->method('getTable')
            ->with('catalogrule_product_price')
            ->willReturn('prefix_catalogrule_product_price');

        $result = $rule->getAllRulePricesFromProduct($date, $websiteId, $productIds);

        $this->assertSame([], $result);
    }
}
