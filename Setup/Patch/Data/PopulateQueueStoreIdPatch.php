<?php

namespace Algolia\AlgoliaSearch\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchInterface;

class PopulateQueueStoreIdPatch implements DataPatchInterface
{
    protected const CHUNK_SIZE = 1000;

    public function __construct(
        protected ModuleDataSetupInterface $moduleDataSetup,
    ) {}

    /**
     * @inheritDoc
     */
    public function apply(): PatchInterface
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('algoliasearch_queue');

        $connection->startSetup();

        // Use a cursor to loop through all rows while allowing NULL store_id
        // i.e. store could not be determined from data and row should remain untouched
        $cursor = 0;
        while (true) {
            $select = $connection->select()
                ->from($table, ['job_id', 'data'])
                ->where('store_id IS NULL AND job_id > ?', $cursor)
                ->order('job_id')
                ->limit(self::CHUNK_SIZE);

            $rows = $connection->fetchAll($select);

            if ($rows === []) {
                break;
            }

            $jobIdsByStoreId = [];
            foreach ($rows as $row) {
                $decoded = json_decode((string) $row['data'], true);
                if (isset($decoded['storeId']) && is_numeric($decoded['storeId'])) {
                    $jobIdsByStoreId[(int) $decoded['storeId']][] = (int) $row['job_id'];
                }
            }

            foreach ($jobIdsByStoreId as $storeId => $jobIds) {
                $connection->update(
                    $table,
                    ['store_id' => $storeId],
                    [$connection->quoteInto('job_id IN (?)', $jobIds)]
                );
            }

            $cursor = (int) end($rows)['job_id'];
        }

        $connection->endSetup();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
