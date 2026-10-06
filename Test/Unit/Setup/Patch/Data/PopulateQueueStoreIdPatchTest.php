<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Setup\Patch\Data;

use Algolia\AlgoliaSearch\Setup\Patch\Data\PopulateQueueStoreIdPatch;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class PopulateQueueStoreIdPatchTest extends TestCase
{
    /** @var array<int, array{bind: array, where: array}> */
    private array $updates = [];

    /** @var array<int, mixed> */
    private array $whereArguments = [];

    /** @var array<int, array> */
    private array $chunks = [];

    private function createPatch(array $chunks): PopulateQueueStoreIdPatch
    {
        $this->updates = [];
        $this->whereArguments = [];
        $this->chunks = $chunks;

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnCallback(function (...$arguments) use ($select) {
            $this->whereArguments[] = $arguments;

            return $select;
        });
        $select->method('order')->willReturnSelf();
        $select->method('limit')->willReturnSelf();

        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('select')->willReturn($select);
        $adapter->method('startSetup');
        $adapter->method('endSetup');
        $adapter->method('quoteInto')
            ->willReturnCallback(static function (string $text, $value): string {
                return str_replace(
                    '?',
                    implode(',', array_map('strval', (array) $value)),
                    $text
                );
            });
        $adapter->method('fetchAll')
            ->willReturnCallback(function () {
                return array_shift($this->chunks) ?? [];
            });
        $adapter->method('update')
            ->willReturnCallback(function (string $table, array $bind, array $where) {
                $this->updates[] = ['bind' => $bind, 'where' => $where];

                return 1;
            });

        $moduleDataSetup = $this->createStub(ModuleDataSetupInterface::class);
        $moduleDataSetup->method('getConnection')->willReturn($adapter);
        $moduleDataSetup->method('getTable')->willReturnArgument(0);

        return new PopulateQueueStoreIdPatch($moduleDataSetup);
    }

    public function testBackfillsStoreIdsFromJobData(): void
    {
        $patch = $this->createPatch([
            [
                ['job_id' => 1, 'data' => '{"storeId":1,"entityIds":[1,2]}'],
                ['job_id' => 2, 'data' => '{"storeId":2,"entityIds":[3]}'],
                ['job_id' => 3, 'data' => '{"storeId":"1","entityIds":[4]}'],
            ],
        ]);

        $patch->apply();

        $this->assertCount(2, $this->updates);
        $this->assertSame(['store_id' => 1], $this->updates[0]['bind']);
        $this->assertSame(['job_id IN (1,3)'], $this->updates[0]['where']);
        $this->assertSame(['store_id' => 2], $this->updates[1]['bind']);
        $this->assertSame(['job_id IN (2)'], $this->updates[1]['where']);
    }

    public function testLeavesUnresolvableRowsNull(): void
    {
        $patch = $this->createPatch([
            [
                ['job_id' => 1, 'data' => '{"storeId":1,"entityIds":[1]}'],
                ['job_id' => 2, 'data' => '[1,false]'],
                ['job_id' => 3, 'data' => 'not-json'],
                ['job_id' => 4, 'data' => '{"entityIds":[2]}'],
                ['job_id' => 5, 'data' => '{"storeId":"not-a-number"}'],
            ],
        ]);

        $patch->apply();

        $this->assertCount(1, $this->updates);
        $this->assertSame(['store_id' => 1], $this->updates[0]['bind']);
        $this->assertSame(['job_id IN (1)'], $this->updates[0]['where']);
    }

    public function testAdvancesCursorAcrossChunks(): void
    {
        $patch = $this->createPatch([
            [
                ['job_id' => 1, 'data' => '{"storeId":1,"entityIds":[1]}'],
                ['job_id' => 2, 'data' => '[1,false]'],
            ],
            [
                ['job_id' => 3, 'data' => '{"storeId":2,"entityIds":[2]}'],
            ],
        ]);

        $patch->apply();

        $this->assertCount(3, $this->whereArguments);
        $this->assertSame(0, $this->whereArguments[0][1]); // cursor start
        $this->assertSame(2, $this->whereArguments[1][1]); // cursor after chunk 1
        $this->assertSame(3, $this->whereArguments[2][1]); // cursor after chunk 2
        $this->assertSame(['store_id' => 1], $this->updates[0]['bind']);
        $this->assertSame(['job_id IN (1)'], $this->updates[0]['where']);
        $this->assertSame(['store_id' => 2], $this->updates[1]['bind']);
        $this->assertSame(['job_id IN (3)'], $this->updates[1]['where']);
    }

    public function testEmptyTablePerformsNoUpdates(): void
    {
        $patch = $this->createPatch([]);

        $patch->apply();

        $this->assertSame([], $this->updates);
        $this->assertCount(1, $this->whereArguments);
    }

    public function testMetadata(): void
    {
        $patch = $this->createPatch([]);

        $this->assertSame([], PopulateQueueStoreIdPatch::getDependencies());
        $this->assertSame([], $patch->getAliases());
    }
}
